<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'order_id',
        'payment_provider_id',
        'type',
        'status',
        'amount',
        'currency',
        'gateway_order_id',
        'gateway_payment_id',
        'gateway_refund_id',
        'signature',
        'payload',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payload' => 'array',
        'notes' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentProvider()
    {
        return $this->belongsTo(PaymentProvider::class);
    }
}
