<?php

namespace App\Models\Banhang;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'branch_id',
        'stock', // Changed from 'stock_quantity' to 'stock'
    ];

    protected $casts = [
        'stock' => 'float', // Changed from 'stock_quantity' to 'stock', ensure it's float for precision
    ];

    /**
     * Get the product that owns the stock entry.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the branch that owns the stock entry.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
