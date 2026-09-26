<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /**
     * Order amounts are always whole rupees.
     *
     * Paise are never charged, never shown and never handed to a payment
     * gateway, so every place that derives an order amount runs it through here.
     * Rounding at the point the amount is derived (rather than at display time)
     * is what keeps the figure on the checkout page, the figure stored on the
     * order and the figure sent to Razorpay identical, instead of showing a
     * customer one number and charging them another.
     */
    public static function roundAmount(float $amount): float
    {
        return round(max(0, $amount));
    }

    protected $fillable = [
        'user_id',
        'checkout_token',
        'payment_provider_id',
        'status',
        'cancel_reason',
        'cancelled_at',
        'payment_method',
        'payment_status',
        'paid_at',
        'refund_status',
        'transaction_id',
        'gateway_order_id',
        'payment_meta',
        'total',
        'refunded_total',
        'stock_deducted',
        'refunded_at',
        'billing_address',
        'shipping_address',
    ];

    protected $casts = [
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'payment_meta' => 'array',
        'cancelled_at' => 'datetime',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
        'refunded_total' => 'decimal:2',
        'stock_deducted' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function paymentProvider()
    {
        return $this->belongsTo(PaymentProvider::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function refunds()
    {
        return $this->hasMany(OrderRefund::class);
    }

    public function returnRequests()
    {
        return $this->hasMany(ReturnRequest::class);
    }
}
