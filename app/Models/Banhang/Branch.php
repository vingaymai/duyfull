<?php

namespace App\Models\Banhang; // Đảm bảo đúng namespace

use App\Models\User; // <--- Dòng này rất quan trọng để import lớp User
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $table = 'branches'; // Đảm bảo tên bảng đúng nếu không phải 'branches'

    protected $fillable = [
        'name',
        'address',
        // Thêm các thuộc tính khác của chi nhánh ở đây
    ];

    /**
     * Định nghĩa mối quan hệ Many-to-Many với User.
     * Một chi nhánh có thể được quản lý bởi nhiều người dùng.
     */
    public function users()
    {
        // Tham số thứ 2: tên bảng trung gian
        // Tham số thứ 3: khóa ngoại của model hiện tại trong bảng trung gian (branch_id)
        // Tham số thứ 4: khóa ngoại của model liên quan trong bảng trung gian (user_id)
        return $this->belongsToMany(User::class, 'branches_user', 'branch_id', 'user_id');
    }

    // ... các phương thức và thuộc tính khác
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}

