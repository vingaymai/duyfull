<?php

namespace App\Models\Banhang;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'date_of_birth',
        'gender',
        'total_spent',
        'total_visits',
        'active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'sold_by_weight' => 'boolean', // Ensures boolean casting
        'track_stock' => 'boolean',    // Ensures boolean casting
        'active' => 'boolean',         // Ensures boolean casting
    ];
}
