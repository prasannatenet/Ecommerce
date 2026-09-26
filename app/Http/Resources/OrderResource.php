<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesStorefrontUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer order.
 *
 * Address payloads are filtered before they leave the server: `meta` on the
 * order is a gateway blob that can contain a provider's raw response, and the
 * billing address legitimately differs from the shipping one only in the fields
 * a customer already sees on their own receipt.
 *
 * @mixin \App\Models\Order
 */
class OrderResource extends JsonResource
{
    use ResolvesStorefrontUrls;

    /** Address keys safe to hand to the storefront. */
    private const ADDRESS_KEYS = [
        'name', 'phone', 'email', 'line1', 'line2',
        'city', 'state', 'postal_code', 'zip', 'country', 'landmark',
    ];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->id,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'paid_at' => optional($this->paid_at)->toIso8601String(),
            'cancelled_at' => optional($this->cancelled_at)->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,

            'subtotal' => (float) max(0, (float) $this->total - (float) $this->refunded_total),
            'total' => (float) $this->total,
            'refunded_total' => (float) $this->refunded_total,
            'refund_status' => $this->refund_status,

            'shipping_address' => $this->address($this->shipping_address),
            'billing_address' => $this->address($this->billing_address),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),

            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }

    /**
     * Whitelist the address keys the storefront is allowed to read back.
     *
     * @param  array<string, mixed>|null  $address
     * @return array<string, mixed>
     */
    private function address(?array $address): array
    {
        $address ??= [];

        return array_intersect_key($address, array_flip(self::ADDRESS_KEYS));
    }
}
