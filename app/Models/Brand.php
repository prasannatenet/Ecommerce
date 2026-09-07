<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Brand extends Model
{
    protected $fillable = ['name','slug','logo','description'];

    // auto-generate slug from name for create operation and update operation
    protected static function boot()
    {
        parent::boot(); 
        static::creating(function ($brand) {
            $brand->slug = Str::slug($brand->name);
        });
        static::updating(function ($brand) {
            $brand->slug = Str::slug($brand->name);
        });
    }
    public function products() {
        return $this->hasMany(Product::class);
    }
}