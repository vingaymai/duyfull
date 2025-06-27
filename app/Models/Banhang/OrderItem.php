<?php

namespace App\Models\Banhang;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Banhang\Order;
use App\Models\Banhang\Product;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name_at_time_of_order',
        'quantity',
        'returned_quantity', // Added for returns
        'unit_price',
        'subtotal',
        'modifiers_options_notes',
    ];

    protected $casts = [
        'quantity' => 'float',
        'returned_quantity' => 'float', // Cast to float
        'unit_price' => 'float',
        'subtotal' => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
