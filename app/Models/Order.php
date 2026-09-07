<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Shipment;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'payment_provider_id',
        'status',
        'cancel_reason',
        'cancelled_at',
        'payment_method',
        'payment_status',
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
