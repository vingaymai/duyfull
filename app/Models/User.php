<?php

namespace App\Models;

use App\Models\Banhang\Branch;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles; // <-- THÊM DÒNG NÀY



class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles; // <-- THÊM 'HasRoles' VÀO ĐÂY

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'active', // <-- THÊM DÒNG NÀY (nếu chưa có)
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'active' => 'boolean', // <-- THÊM DÒNG NÀY để Laravel tự động cast về boolean
    ];

    /**
     * Định nghĩa mối quan hệ Many-to-Many với Branch.
     * Người dùng có thể quản lý nhiều chi nhánh.
     */
    public function branches()
    {
        // Tham số thứ 2: tên bảng trung gian
        // Tham số thứ 3: khóa ngoại của model hiện tại trong bảng trung gian (user_id)
        // Tham số thứ 4: khóa ngoại của model liên quan trong bảng trung gian (branch_id)
        return $this->belongsToMany(Branch::class, 'branch_user', 'user_id', 'branch_id');
    }

}
