<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VariationImage extends Model
{
    protected $fillable = ['product_variation_id', 'path', 'is_primary'];

    public function variation() { return $this->belongsTo(ProductVariation::class, 'product_variation_id'); }
}
