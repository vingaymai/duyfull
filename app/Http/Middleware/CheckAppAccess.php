<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth; // Đảm bảo đã import Auth

class CheckAppAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string ...$app_ids_or_permissions // Có thể nhận tên ứng dụng hoặc tên quyền trực tiếp
     */
    public function handle(Request $request, Closure $next, ...$app_ids_or_permissions): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        // Nếu người dùng là 'admin', cho phép truy cập mọi thứ (tùy chọn)
        if ($user->hasRole('admin')) {
            return $next($request);
        }

        // Chuyển đổi tên app_id thành tên permission
        $requiredPermissions = collect($app_ids_or_permissions)->map(function ($id) {
            // Nếu bạn truyền trực tiếp tên permission (ví dụ: 'access_app_sales')
            if (str_starts_with($id, 'access_app_')) {
                return $id;
            }
            // Nếu bạn chỉ truyền id ứng dụng (ví dụ: 'sales'), chuyển đổi thành tên permission chuẩn
            return 'access_app_' . $id;
        })->toArray();

        // Kiểm tra xem người dùng có bất kỳ quyền nào trong số các quyền yêu cầu không
        // Spatie cho phép kiểm tra nhiều quyền cùng lúc: canAny
        if ($user->hasAnyPermission($requiredPermissions)) {
            return $next($request);
        }

        // Nếu không có quyền truy cập
        return response()->json(['message' => 'Forbidden: You do not have access to this application module.'], 403);
    }
}
