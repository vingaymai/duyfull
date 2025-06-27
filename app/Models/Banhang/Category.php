<?php

namespace App\Models\Banhang;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    /**
     * Các thuộc tính có thể gán hàng loạt (mass assignable).
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'position',
        'active', // Đảm bảo cột này có
    ];

    /**
     * Định nghĩa quan hệ với danh mục cha (parent).
     * Một danh mục có thể có một danh mục cha.
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Định nghĩa quan hệ với các danh mục con (children).
     * Một danh mục có thể có nhiều danh mục con.
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
}
