<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
 // Nếu bạn dùng Spatie để gán vai trò

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Tạo người dùng test
        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'], // Kiểm tra xem email đã tồn tại chưa
            [
                'name' => 'Admin Test',
                'password' => Hash::make('password'), // Mật khẩu: password
                'active' => true,
                'email_verified_at' => now(), // Đặt verified để tránh lỗi xác thực email
            ]
        );

        // Nếu bạn đang sử dụng Spatie Laravel-Permission và muốn gán vai trò 'admin'
        // Đảm bảo vai trò 'admin' đã tồn tại trong database trước khi chạy seeder này
        if (Schema::hasTable('roles')) { // Kiểm tra bảng roles có tồn tại không
            $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
            if (!$user->hasRole('admin')) {
                $user->assignRole('admin');
            }
        }
        
        $this->command->info('Test user created/updated: admin@example.com with password "password"');
    }
}
