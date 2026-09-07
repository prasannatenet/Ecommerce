<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariation extends Model
{
    protected $fillable = ['product_id','sku','price','stock','attributes','is_active'];
    protected $casts = ['attributes' => 'array'];

    public function product() { return $this->belongsTo(Product::class); }
}