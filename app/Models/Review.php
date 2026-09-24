<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    protected array $imagePathsForDeletion = [];

    protected static function booted(): void
    {
        static::deleting(function (Review $review): void {
            $review->imagePathsForDeletion = $review->images()->pluck('path')->all();
        });

        static::deleted(function (Review $review): void {
            if ($review->imagePathsForDeletion !== []) {
                Storage::disk('public')->delete($review->imagePathsForDeletion);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function images()
    {
        return $this->hasMany(ReviewImage::class)->orderBy('id');
    }
}
