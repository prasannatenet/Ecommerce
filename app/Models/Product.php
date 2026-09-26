<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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

    public function videos()
    {
        return $this->hasMany(ProductVideo::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'product_tag')->withTimestamps();
    }

    public function combos()
    {
        return $this->belongsToMany(Combo::class, 'combo_product');
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

    /**
     * Variations that drive the price, preferring the already-loaded relation so
     * listing pages do not fire one query per product.
     */
    public function pricingVariations(): Collection
    {
        $variations = $this->relationLoaded('variations')
            ? $this->variations
            : $this->variations()->get();

        $active = $variations->where('is_active', true)->values();

        return $active->isNotEmpty() ? $active : $variations->values();
    }

    /** Does this product sell through variations? */
    public function hasVariations(): bool
    {
        return $this->pricingVariations()->isNotEmpty();
    }

    /**
     * The variation whose price is quoted for this product everywhere: the
     * product cards, the product detail page, quick view, search, the combo
     * maths and the cart all resolve it through this one method, so a customer
     * never sees one price on a listing and another after clicking through.
     *
     * Buyable variations win over out-of-stock ones, because quoting a price
     * the customer cannot actually pay for is what produced mismatches such as
     * a card showing Rs 99 while the detail page showed Rs 1,500,000.
     */
    public function defaultVariation(): ?ProductVariation
    {
        $variations = $this->pricingVariations();

        if ($variations->isEmpty()) {
            return null;
        }

        $byPrice = fn (ProductVariation $variation) => $variation->effectivePrice();

        $inStock = $variations
            ->filter(fn (ProductVariation $variation) => (int) ($variation->stock ?? 0) > 0)
            ->sortBy($byPrice);

        if ($inStock->isNotEmpty()) {
            return $inStock->first();
        }

        return $variations->sortBy($byPrice)->first();
    }

    /**
     * Cheapest buyable variation by the price the customer actually pays.
     *
     * @deprecated Use defaultVariation(); it is the single source of the price
     *             shown to customers everywhere in the storefront.
     */
    public function cheapestVariation(): ?ProductVariation
    {
        return $this->defaultVariation();
    }

    /** Price shown to customers – variation aware, sale price included. */
    public function effectivePrice(): float
    {
        $variation = $this->cheapestVariation();

        if ($variation) {
            return $variation->effectivePrice();
        }

        return (float) ($this->sale_price ?? $this->base_price ?? 0);
    }

    /** Regular price struck through next to effectivePrice(). */
    public function regularPrice(): float
    {
        $variation = $this->cheapestVariation();

        if ($variation) {
            return (float) $variation->price;
        }

        return (float) ($this->base_price ?? 0);
    }

    /** Discount percentage of the displayed price, or null when not discounted. */
    public function discountPercentage(): ?float
    {
        $regular = $this->regularPrice();
        $effective = $this->effectivePrice();

        if ($regular <= 0 || $effective >= $regular) {
            return null;
        }

        return round((($regular - $effective) / $regular) * 100, 2);
    }

    /** Is the displayed price discounted? */
    public function hasDiscount(): bool
    {
        return $this->discountPercentage() !== null;
    }

    /**
     * Regular/selling price ranges used by the admin product page. Ranges differ
     * once a product is priced per variation.
     */
    public function pricingSummary(): array
    {
        $regularPrices = collect([$this->regularPrice()])->filter(fn ($price) => $price > 0);
        $effectivePrices = collect([$this->effectivePrice()])->filter(fn ($price) => $price > 0);

        if ($this->hasVariations()) {
            $regularPrices = $this->pricingVariations()
                ->map(fn (ProductVariation $variation) => (float) $variation->price)
                ->filter(fn ($price) => $price > 0)
                ->values();
            $effectivePrices = $this->pricingVariations()
                ->map(fn (ProductVariation $variation) => $variation->effectivePrice())
                ->filter(fn ($price) => $price > 0)
                ->values();
        }

        return [
            'regular_min' => (float) ($regularPrices->min() ?? 0),
            'regular_max' => (float) ($regularPrices->max() ?? 0),
            'effective_min' => (float) ($effectivePrices->min() ?? 0),
            'effective_max' => (float) ($effectivePrices->max() ?? 0),
            'discount_percentage' => $this->discountPercentage(),
        ];
    }

    /**
     * Price shown to customers – variation aware, sale price included.
     *
     * This deliberately delegates to effectivePrice() so the combo maths, the
     * search results and the admin combo preview can never quote a different
     * number from the one the customer sees and pays. It used to look only at
     * the cheapest variation's raw price and fall back to base_price, which
     * ignored a product's own sale_price whenever product_type was "variable"
     * and no variations existed yet.
     */
    public function getDisplayPriceAttribute()
    {
        return $this->effectivePrice();
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
