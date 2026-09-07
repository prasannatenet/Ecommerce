<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Shipment;

class DeliveryPartner extends Model
{
    protected $fillable = [
        'name',
        'code',
        'contact_email',
        'contact_phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }
}
