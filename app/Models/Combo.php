<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Combo extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'discount_type',
        'discount_value',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'starts_at'      => 'datetime',
        'expires_at'     => 'datetime',
        'is_active'      => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($combo) {
            if (empty($combo->slug)) {
                $combo->slug = $combo->generateUniqueSlug($combo->name);
            }
        });
    }

    /**
     * Generate a unique slug for the combo (suffix -2, -3, ... when taken).
     */
    public function generateUniqueSlug($name, $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'combo';
        $slug = $base;
        $i = 1;

        while (static::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $i++;
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    /* ───── Relationships ───── */

    public function products()
    {
        return $this->belongsToMany(Product::class, 'combo_product')
                    ->withPivot('sort_order')
                    ->orderByPivot('sort_order')
                    ->withTimestamps();
    }

    /* ───── Scopes ───── */

    /** Combos that are enabled and inside their schedule window. */
    public function scopeActive($query)
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now));
    }

    /* ───── Helpers ───── */

    /** Is this combo currently shown to customers? */
    public function isLive(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /** Combined price of every product in the combo. */
    public function productsTotal(): float
    {
        return round((float) $this->products->sum(fn ($product) => (float) $product->display_price), 2);
    }

    /** Discount amount for the whole combo (never more than the total). */
    public function discountAmount(): float
    {
        $total = $this->productsTotal();
        $discount = $this->discount_type === 'percent'
            ? $total * ((float) $this->discount_value / 100)
            : (float) $this->discount_value;

        return round(min(max($discount, 0), $total), 2);
    }

    /** Final combo price. */
    public function comboPrice(): float
    {
        return round($this->productsTotal() - $this->discountAmount(), 2);
    }

    /** Savings as a percentage of the combined total. */
    public function savingsPercent(): float
    {
        $total = $this->productsTotal();

        return $total > 0 ? round(($this->discountAmount() / $total) * 100, 1) : 0.0;
    }
}
