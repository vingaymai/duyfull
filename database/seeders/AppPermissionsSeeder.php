<?php
// Trong database/seeders/AppPermissionsSeeder.php (hoặc seeder hiện có của bạn)
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
class AppPermissionsSeeder extends Seeder
{
    public function run()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $app_ids = [
            'sales', 'ql_sanpham', 'ql_danhmuc_sanpham', 'ql_chinhanh',
            'ql_khachhang', 'ql_nguoidung', 'ql_donhang', 'baocaothongke'
        ];

        foreach ($app_ids as $app_id) {
            Permission::firstOrCreate(['name' => 'access_app_' . $app_id, 'guard_name' => 'web']);
        }

        // Gán các quyền này cho vai trò 'admin' (ví dụ)
        $adminRole = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo(collect($app_ids)->map(fn($id) => 'access_app_' . $id)->toArray());
        }
    }
}

