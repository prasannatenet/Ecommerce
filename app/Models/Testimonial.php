<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    protected $fillable = [
        'name',
        'designation',
        'content',
        'rating',
        'initials',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'rating' => 'float',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];
}
