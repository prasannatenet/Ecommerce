<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Shipment;

class DeliveryPartner extends Model
{
    protected $fillable = [
        'name',
        'code',
        'driver',
        'contact_email',
        'contact_phone',
        'is_active',
        'api_key',
        'client_id',
        'webhook_secret',
        'base_url',
        'is_sandbox',
        'is_default',
        'auto_book_on',
        'auto_update_order_status',
        'tracking_url_template',
        'status_map',
        'config',
        'last_connection_test_at',
        'last_connection_test_ok',
        'last_error',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'api_key' => 'encrypted',
        'webhook_secret' => 'encrypted',
        'is_sandbox' => 'boolean',
        'is_default' => 'boolean',
        'auto_update_order_status' => 'boolean',
        'status_map' => 'array',
        'config' => 'array',
        'last_connection_test_at' => 'datetime',
        'last_connection_test_ok' => 'boolean',
    ];

    protected $hidden = [
        'api_key',
        'webhook_secret',
    ];

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function isIntegrated(): bool
    {
        return $this->driver !== null && $this->driver !== '' && $this->driver !== 'manual';
    }

    public function shouldAutoBookOn(string $orderStatus): bool
    {
        if (! $this->is_active || ! $this->isIntegrated()) {
            return false;
        }

        return match ($this->auto_book_on) {
            'processing' => $orderStatus === 'processing',
            'shipped' => $orderStatus === 'shipped',
            'both' => in_array($orderStatus, ['processing', 'shipped'], true),
            default => false,
        };
    }

    public function configValue(string $key, mixed $default = null): mixed
    {
        $config = $this->config ?? [];

        return $config[$key] ?? $default;
    }

    public function trackingUrlFor(string $waybill): ?string
    {
        $template = trim((string) ($this->tracking_url_template ?? ''));

        if ($template === '') {
            return null;
        }

        return str_replace(['{awb}', '{tracking_number}', '{waybill}'], $waybill, $template);
    }
}
