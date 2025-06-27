<?php
// Trong database/seeders/PermissionsSeeder.php (hoặc một seeder bất kỳ)
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        Permission::firstOrCreate(['name' => 'view products']);
        Permission::firstOrCreate(['name' => 'manage products']);
        Permission::firstOrCreate(['name' => 'access_app_dashboard']);
        Permission::firstOrCreate(['name' => 'access_app_users']);
        Permission::firstOrCreate(['name' => 'access_app_products']);
        Permission::firstOrCreate(['name' => 'access_app_orders']);
        Permission::firstOrCreate(['name' => 'access_app_branches']);
        // ... thêm các quyền khác cho các module ứng dụng của bạn

        // Create roles and assign permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all()); // Admin có tất cả quyền

        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerRole->givePermissionTo(['view products', 'access_app_dashboard', 'access_app_products', 'access_app_orders']);

        $employeeRole = Role::firstOrCreate(['name' => 'employee']);
        $employeeRole->givePermissionTo(['view products', 'access_app_dashboard', 'access_app_orders']);
    }
}