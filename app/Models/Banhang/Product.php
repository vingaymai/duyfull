<?php

namespace App\Models\Banhang;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'barcode',
        'description',
        'image_url', // Đã thêm: Đường dẫn đến ảnh sản phẩm
        'base_price',
        'cost_price',
        'sold_by_weight',
        'unit',
        'track_stock',
        'active',
    ];
//cấu hình phân quyền thể hiện sản phẩm
    protected $casts = [
        'track_stock' => 'boolean',
        'sold_by_weight' => 'boolean',
        'active' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    /**
     * Get the category that owns the product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the product options for the product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function productOptions()
    {
        return $this->hasMany(ProductOption::class);
    }

    /**
     * Get the product stocks for the product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function productStocks()
    {
        return $this->hasMany(ProductStock::class);
    }

    /**
     * Get the products that are included in this product (if it's a combo).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function productIncludes()
    {
        // This means THIS product includes OTHER products.
        return $this->hasMany(ProductInclude::class, 'product_id');
    }

    /**
     * Get the products that include this product (if this product is a component of a combo).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function includedInProducts()
    {
        // This means OTHER products include THIS product.
        // The foreign key 'included_product_id' on 'product_includes' refers to this product's ID.
        return $this->hasMany(ProductInclude::class, 'included_product_id');
    }

    /**
     * Get the modifiers for the product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function modifiers()
    {
        return $this->hasMany(Modifier::class);
    }

    /**
     * Get the order items for the product.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
