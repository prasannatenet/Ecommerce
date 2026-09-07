<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'type',
        'amount',
        'buy_quantity',
        'get_quantity',
        'reward_coins',
        'max_uses',
        'used_count',
        'min_order_amount',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'buy_quantity' => 'integer',
        'get_quantity' => 'integer',
        'reward_coins' => 'integer',
        'min_order_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];
}
