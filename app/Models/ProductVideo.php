<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductVideo extends Model
{
    protected $fillable = ['product_id','path','is_primary'];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function product() { return $this->belongsTo(Product::class); }

    /** Return the full URL for the video, or null. */
    public function getUrlAttribute(): ?string
    {
        if (empty($this->path)) {
            return null;
        }

        return Storage::url($this->path);
    }

    /**
     * URL used for playback. Videos are streamed through the application because
     * PHP's built-in server (and some web servers) ignore Range requests, which
     * stops a browser from starting a non-faststart MP4 until the whole file has
     * downloaded. Laravel's file response answers with 206 partial content.
     */
    public function getStreamUrlAttribute(): ?string
    {
        if (empty($this->path) || ! $this->exists) {
            return $this->url;
        }

        return route('product.videos.stream', $this);
    }
}
