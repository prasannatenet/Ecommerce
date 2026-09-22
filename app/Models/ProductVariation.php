<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariation extends Model
{
    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'description',
        'discount_type',
        'discount_value',
        'sale_price',
        'stock',
        'attributes',
        'is_active',
    ];

    protected $casts = ['attributes' => 'array'];

    public function product() { return $this->belongsTo(Product::class); }

    public function images() { return $this->hasMany(VariationImage::class, 'product_variation_id'); }

    /**
     * Effective selling price (sale price when a valid discount exists).
     */
    public function effectivePrice(): float
    {
        if ($this->sale_price !== null && (float) $this->sale_price > 0 && (float) $this->sale_price < (float) $this->price) {
            return (float) $this->sale_price;
        }

        return (float) $this->price;
    }

    /**
     * Discount percentage displayed to customers, if any.
     */
    public function discountPercentage(): ?float
    {
        $price = (float) $this->price;

        if ($price <= 0 || $this->effectivePrice() >= $price) {
            return null;
        }

        return round((($price - $this->effectivePrice()) / $price) * 100, 2);
    }
}