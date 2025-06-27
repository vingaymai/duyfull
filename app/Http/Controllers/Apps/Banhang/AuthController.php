<?php

namespace App\Http\Controllers\Apps\Banhang;

use App\Http\Controllers\Controller;
use App\Models\User; // Đảm bảo import User model
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Lấy quyền hạn của người dùng nếu bạn sử dụng Spatie/Permission
        $permissions = [];
        if (method_exists($user, 'getAllPermissions')) { // Kiểm tra phương thức tồn tại
            $permissions = $user->getAllPermissions()->pluck('name')->toArray();
        } elseif (method_exists($user, 'permissions')) { // Hoặc cách khác để lấy quyền nếu có
             $permissions = $user->permissions->pluck('name')->toArray();
        }

        return response()->json([
            'user' => $user,
            'permissions' => $permissions, // Trả về danh sách quyền hạn
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials, $request->remember)) { // Thêm $request->remember
            $user = Auth::user();
            // Lấy tất cả quyền của người dùng
            $permissions = $user->getAllPermissions()->pluck('name')->toArray();
            $roles = $user->getRoleNames()->toArray(); // Lấy tên các vai trò

            return response()->json([
                'message' => 'Đăng nhập thành công!',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $roles,
                    'permissions' => $permissions,
                ],
            ], 200);
        }

        // Nếu đăng nhập thất bại
        throw ValidationException::withMessages([
            'email' => ['Thông tin đăng nhập không hợp lệ.'],
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Đăng xuất thành công.'], 200);
    }

    public function user(Request $request)
    {
        // Trả về thông tin người dùng đang đăng nhập và quyền của họ
        $user = $request->user();
        if ($user) {
            $permissions = $user->getAllPermissions()->pluck('name')->toArray();
            $roles = $user->getRoleNames()->toArray();
            return response()->json([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles,
                'permissions' => $permissions,
            ]);
        }
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    public function showApp()
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user()->load('roles.permissions');
        $user->load('roles');

        return Inertia::render('Hello', [
            'user' => $user->toArray(),
            'userRoles' => $user->roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'accessible_apps' => $role->accessible_apps,
                ];
            })->toArray(),
        ]);
    }
}
