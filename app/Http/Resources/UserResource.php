<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesStorefrontUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The authenticated customer.
 *
 * `roles` lets the React app hide or show admin-only chrome without a second
 * round trip, and `gehna_coins` is the store's loyalty balance. The password
 * hash and remember token are already hidden by the User model, but the
 * explicit field list here means a column added to users later can never leak
 * into an auth response by accident.
 *
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    use ResolvesStorefrontUrls;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'gehna_coins' => (int) $this->gehna_coins,
            'roles' => $this->getRoleNames()->values(),
            'is_admin' => $this->hasRole('admin'),
            'email_verified_at' => optional($this->email_verified_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
