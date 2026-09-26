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

    /**
     * Every redemption of this coupon, one row per customer.
     *
     * A coupon is a one-per-customer offer, so this is what tells us whether a
     * given shopper has already spent it.
     */
    public function uses()
    {
        return $this->hasMany(CouponUse::class);
    }

    /** Has this specific customer already redeemed this coupon? */
    public function isRedeemedBy(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        return $this->uses()->where('user_id', $userId)->exists();
    }
}
