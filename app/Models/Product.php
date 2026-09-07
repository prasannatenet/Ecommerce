<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'brand_id',
        'category_id',
        'name',
        'audience',
        'material_type',
        'slug',
        'short_description',
        'description',
        'base_price',
        'sale_price',
        'discount_type',
        'discount_value',
        'primary_image',
        'is_active',
        'product_type',
        'sku',
        'stock',
        'manage_stock',
        'weight',
        'tax_class',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'manage_stock' => 'boolean',
        'base_price'   => 'decimal:2',
        'sale_price'   => 'decimal:2',
        'discount_value' => 'decimal:2',
        'weight'       => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    /* ───── Relationships ───── */

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variations()
    {
        return $this->hasMany(ProductVariation::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'product_tag')->withTimestamps();
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'product_attributes')
                    ->withPivot('position')
                    ->withTimestamps()
                    ->orderBy('product_attributes.position');
    }

    /* ───── Helpers ───── */

    /** Is this a simple product (no variations)? */
    public function isSimple(): bool
    {
        return $this->product_type === 'simple';
    }

    /** Is this a variable product (has variations)? */
    public function isVariable(): bool
    {
        return $this->product_type === 'variable';
    }

    /** Get display price – sale price takes priority, then base, then lowest variation */
    public function getDisplayPriceAttribute()
    {
        if ($this->isSimple()) {
            return $this->sale_price ?? $this->base_price;
        }

        // Variable product: use lowest variation price
        $min = $this->variations()->where('is_active', true)->min('price');
        return $min ?? $this->base_price;
    }

    /** Check if product is on sale */
    public function getIsOnSaleAttribute(): bool
    {
        if ($this->isSimple()) {
            return $this->sale_price !== null && $this->sale_price < $this->base_price;
        }
        return false;
    }

    /** Get total stock across all variations (or own stock for simple) */
    public function getTotalStockAttribute(): int
    {
        if ($this->isSimple()) {
            return $this->stock;
        }
        return $this->variations()->where('is_active', true)->sum('stock');
    }

    /** Legacy helper – keep backward compatibility */
    public function price()
    {
        return $this->display_price;
    }
}
