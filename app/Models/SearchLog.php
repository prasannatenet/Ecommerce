<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'searchable',
        'searched_at',
    ];

    protected $casts = [
        'searched_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Return the most recent distinct searches for the given user (up to $limit).
     */
    public static function recentForUser(?int $userId, int $limit = 5): array
    {
        if (!$userId) {
            return [];
        }

        return static::where('user_id', $userId)
            ->orderBy('searched_at', 'desc')
            ->distinct()
            ->pluck('searchable')
            ->take($limit)
            ->toArray();
    }
}
