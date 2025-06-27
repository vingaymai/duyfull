<?php

namespace App\Http\Controllers\Apps\Banhang;


use App\Http\Controllers\Controller; // <-- Cần thiết
use App\Models\User;
use App\Models\Banhang\Branch; // <--- Rất quan trọng: Đảm bảo đúng đường dẫn namespace của Branch Model
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; // Import JsonResponse để có thể trả về JSON rõ ràng

class BranchManagerController extends Controller
{
    /**
     * Gắn một người dùng làm quản lý cho một chi nhánh.
     * POST /api/users/{user}/branches/{branch}/assign
     *
     * @param int $userId ID của người dùng
     * @param int $branchId ID của chi nhánh
     * @return JsonResponse
     */
    public function assignManager(int $userId, int $branchId): JsonResponse
    {
        $user = User::find($userId);
        $branch = Branch::find($branchId);

        if (!$user || !$branch) {
            return response()->json(['message' => 'Người dùng hoặc Chi nhánh không tìm thấy.'], 404);
        }

        // Gắn (attach) người dùng vào chi nhánh.
        // Phương thức attach sẽ thêm một bản ghi vào bảng user_branches.
        // Nếu bản ghi đã tồn tại, nó sẽ không tạo bản ghi trùng lặp.
        $user->branches()->attach($branchId);

        return response()->json(['message' => 'Người dùng đã được gán làm quản lý cho chi nhánh thành công.']);
    }

    /**
     * Gỡ bỏ (detach) một người dùng khỏi việc quản lý một chi nhánh.
     * DELETE /api/users/{user}/branches/{branch}/remove
     *
     * @param int $userId ID của người dùng
     * @param int $branchId ID của chi nhánh
     * @return JsonResponse
     */
    public function removeManager(int $userId, int $branchId): JsonResponse
    {
        $user = User::find($userId);
        $branch = Branch::find($branchId);

        if (!$user || !$branch) {
            return response()->json(['message' => 'Người dùng hoặc Chi nhánh không tìm thấy.'], 404);
        }

        // Gỡ bỏ (detach) người dùng khỏi chi nhánh.
        // Phương thức detach sẽ xóa bản ghi tương ứng khỏi bảng user_branches.
        $user->branches()->detach($branchId);

        return response()->json(['message' => 'Người dùng đã được gỡ bỏ khỏi chi nhánh thành công.']);
    }

    /**
     * Đồng bộ (sync) các chi nhánh quản lý của một người dùng.
     * Điều này sẽ xóa tất cả các chi nhánh hiện có của người dùng và gán lại các chi nhánh mới.
     * Ví dụ: $user->branches()->sync([1, 2, 3]); // Người dùng sẽ chỉ quản lý chi nhánh 1, 2, 3.
     * POST /api/users/{user}/branches/sync
     *
     * @param Request $request Request chứa mảng branch_ids
     * @param int $userId ID của người dùng
     * @return JsonResponse
     */
    public function syncManagerBranches(Request $request, int $userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['message' => 'Người dùng không tìm thấy.'], 404);
        }

        // Lấy mảng các branch_id từ request. Mặc định là mảng rỗng nếu không có.
        $branchIds = $request->input('branch_ids', []);

        // Đồng bộ các chi nhánh. Các chi nhánh không có trong mảng sẽ bị xóa,
        // các chi nhánh mới sẽ được thêm vào, các chi nhánh hiện có sẽ giữ nguyên.
        $user->branches()->sync($branchIds);

        return response()->json(['message' => 'Chi nhánh của quản lý đã được đồng bộ thành công.']);
    }

    /**
     * Lấy danh sách các chi nhánh mà một người dùng đang quản lý.
     * GET /api/users/{user}/branches
     *
     * @param int $userId ID của người dùng
     * @return JsonResponse
     */
    public function getUserBranches(int $userId): JsonResponse
    {
        // Sử dụng eager loading với 'branches' để tránh vấn đề N+1 query
        $user = User::with('branches')->find($userId);

        if (!$user) {
            return response()->json(['message' => 'Người dùng không tìm thấy.'], 404);
        }

        // Trả về danh sách các chi nhánh
        return response()->json($user->branches);
    }

    /**
     * Lấy danh sách người dùng quản lý cho một chi nhánh cụ thể.
     * GET /api/branches/{branch}/users
     *
     * @param int $branchId ID của chi nhánh
     * @return JsonResponse
     */
    public function getBranchUsers(int $branchId): JsonResponse
    {
        // Sử dụng eager loading với 'users' để tránh vấn đề N+1 query
        $branch = Branch::with('users')->find($branchId);

        if (!$branch) {
            return response()->json(['message' => 'Chi nhánh không tìm thấy.'], 404);
        }

        // Trả về danh sách người dùng
        return response()->json($branch->users);
    }
}
