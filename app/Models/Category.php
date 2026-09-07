<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'parent_id', 'description', 'position', 'image'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($category) {
            $category->slug = Str::slug($category->name);
        });
        static::updating(function ($category) {
            $category->slug = Str::slug($category->name);
        });
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /** Recursive children for nested tree */
    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /** Get full breadcrumb path: Parent > Child > Grandchild */
    public function getBreadcrumbAttribute(): string
    {
        $parts = [];
        $current = $this;

        while ($current) {
            array_unshift($parts, $current->name);
            $current = $current->parent;
        }

        return implode(' → ', $parts);
    }

    /** Check if this is a top-level category */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }
}