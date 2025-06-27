<?php

namespace App\Http\Controllers\Apps\Banhang;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Banhang\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    /**
     * Display a listing of the roles.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // Lấy tất cả vai trò cùng với số lượng người dùng và quyền hạn liên quan
        $roles = Role::withCount('users') // Eager load user count
                     ->with('permissions') // Eager load permissions
                     ->get();
        return response()->json($roles);
    }

    /**
     * Display a listing of all roles (for dropdowns, without pagination/counts).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function all()
    {
        // Lấy tất cả vai trò, bao gồm cả accessible_apps (nếu bạn có cột này)
        // Hiện tại chỉ trả về id, name, description. Nếu cần accessible_apps, bạn phải có cột đó trong bảng roles.
        $roles = Role::all(['id', 'name', 'description']);
        return response()->json($roles);
    }

    /**
     * Display a listing of all permissions.
     * This method is added to provide an API endpoint for fetching all permissions.
     * The frontend's ql_nguoidung.vue uses this to filter 'access_app_' permissions.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function allPermissions()
    {
        $permissions = Permission::all(['id', 'name']); // Lấy ID và tên của tất cả quyền
        return response()->json($permissions);
    }

    /**
     * Show the specified role.
     * This method is crucial for loading role details, including its assigned permissions,
     * when editing a role in the frontend.
     *
     * @param  \Spatie\Permission\Models\Role  $role
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Role $role)
    {
        try {
            // Tải vai trò cùng với các quyền hạn được gán cho nó
            // Eager load permissions to include them in the response
            $role->load('permissions');
            Log::info("Đã tải thông tin vai trò", ['role_id' => $role->id, 'role_name' => $role->name]);
            return response()->json($role);
        } catch (\Exception $e) {
            Log::error('Error loading role: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in file ' . $e->getFile());
            return response()->json(['message' => 'Đã xảy ra lỗi khi tải thông tin vai trò.'], 500);
        }
    }


    /**
     * Store a newly created role in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:roles,name',
                'description' => 'nullable|string|max:255',
                'permissions' => 'nullable|array', // Array of permission names
                'permissions.*' => 'string|exists:permissions,name', // Đảm bảo tên quyền tồn tại
            ]);

            $role = Role::create([
                'name' => $request->name,
                'description' => $request->description,
                'guard_name' => 'web', // Hoặc guard của bạn
            ]);

            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            return response()->json(['message' => 'Vai trò đã được tạo thành công!', 'role' => $role->load('permissions')], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error creating role: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in file ' . $e->getFile());
            return response()->json(['message' => 'Đã xảy ra lỗi khi tạo vai trò.'], 500);
        }
    }

    /**
     * Update the specified role in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Spatie\Permission\Models\Role  $role
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Role $role)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
                'description' => 'nullable|string|max:255',
                'permissions' => 'nullable|array',
                'permissions.*' => 'string|exists:permissions,name',
            ]);

            $role->update([
                'name' => $request->name,
                'description' => $request->description,
            ]);

            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            } else {
                $role->syncPermissions([]); // Remove all permissions if none are provided
            }

            return response()->json(['message' => 'Vai trò đã được cập nhật thành công!', 'role' => $role->load('permissions')], 200);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error updating role: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in file ' . $e->getFile());
            return response()->json(['message' => 'Đã xảy ra lỗi khi cập nhật vai trò.'], 500);
        }
    }

    /**
     * Sync permissions for a specific role.
     * This method is specifically for handling the permission updates from the role management modal.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Spatie\Permission\Models\Role  $role
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncPermissions(Request $request, Role $role)
    {
        try {
            $request->validate([
                'permissions' => 'nullable|array',
                'permissions.*' => 'string|exists:permissions,name',
            ]);

            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            } else {
                $role->syncPermissions([]); // Remove all permissions if none are provided
            }

            return response()->json(['message' => 'Quyền hạn đã được cập nhật thành công cho vai trò ' . $role->name . '!'], 200);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error syncing role permissions: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in file ' . $e->getFile());
            return response()->json(['message' => 'Đã xảy ra lỗi khi cập nhật quyền hạn cho vai trò.'], 500);
        }
    }

    /**
     * Remove the specified role from storage.
     *
     * @param  \Spatie\Permission\Models\Role  $role
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Role $role)
    {
        try {
            $role->delete();
            return response()->json(['message' => 'Vai trò đã được xóa thành công!'], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting role: ' . $e->getMessage());
            return response()->json(['message' => 'Đã xảy ra lỗi khi xóa vai trò.'], 500);
        }
    }
}
