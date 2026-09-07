<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Attribute extends Model
{
    protected $fillable = ['name', 'slug', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($attribute) {
            if (empty($attribute->slug)) {
                $attribute->slug = Str::slug($attribute->name);
            }
        });
    }

    public function values()
    {
        return $this->hasMany(AttributeValue::class)->orderBy('position');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_attributes')
                    ->withPivot('position')
                    ->withTimestamps();
    }
}
