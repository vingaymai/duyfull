<?php

namespace App\Models\Banhang;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'values',
        'position',
    ];

    /**
     * Thuộc tính sẽ được cast sang kiểu dữ liệu cụ thể.
     * `values` được cast thành `array` để Laravel tự động mã hóa/giải mã JSON.
     * @var array
     */
    protected $casts = [
        'values' => 'array', // Đảm bảo trường 'values' luôn được xử lý dưới dạng mảng JSON
    ];

    /**
     * Lấy sản phẩm mà tùy chọn này thuộc về.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
