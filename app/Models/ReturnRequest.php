<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRequest extends Model
{
    protected $fillable = [
        'order_id', 'user_id', 'type', 'status', 'reason', 'admin_note',
        'amount', 'refund_method', 'payout_method', 'payout_details', 'payout_reference', 'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'payout_details' => 'encrypted:array',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(ReturnRequestItem::class); }
}
