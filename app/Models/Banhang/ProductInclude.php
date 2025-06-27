<?php

namespace App\Models\Banhang;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductInclude extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',          // The combo product ID
        'included_product_id', // The component product ID
        'quantity',
    ];

    /**
     * Get the combo product that includes this component.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the included component product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function includedProduct()
    {
        return $this->belongsTo(Product::class, 'included_product_id');
    }
}
