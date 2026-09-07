<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRequestItem extends Model
{
    protected $fillable = ['return_request_id', 'order_item_id', 'quantity', 'amount'];

    protected $casts = ['amount' => 'decimal:2'];

    public function request() { return $this->belongsTo(ReturnRequest::class, 'return_request_id'); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
}
