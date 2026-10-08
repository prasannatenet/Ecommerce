<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-shot 6-digit code the admin panel emails before letting an admin
 * change their password.
 *
 * The row stores the sha256 of the code (never the code itself) plus the
 * hash of the pending new password, so the password only moves once the
 * emailed code has been confirmed. Codes expire after 10 minutes and a used
 * row can never be replayed.
 */
class PasswordVerificationCode extends Model
{
    use HasFactory;

    /** Minutes before a code stops being accepted. */
    public const EXPIRES_IN_MINUTES = 10;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code_hash',
        'user_id',
        'pending_password_hash',
        'ip_address',
        'user_agent',
        'used',
        'used_at',
        'expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used' => 'boolean',
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Hash a 6-digit code the way the column stores it.
     */
    public static function hash(string $code): string
    {
        return hash('sha256', $code);
    }

    /**
     * A cryptographically random 6-digit code, zero padded so it is always
     * exactly six digits (random_int itself is not).
     */
    public static function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Latest still-open challenge for a user: unused, unexpired.
     */
    public function scopeActiveFor(Builder $query, int $userId): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->where('used', false)
            ->where('expires_at', '>', now());
    }

    /**
     * Whether the given plaintext code matches this challenge.
     */
    public function matches(string $code): bool
    {
        return hash_equals($this->code_hash, self::hash($code));
    }

    /**
     * True when the code can no longer be accepted (expired or burned).
     * Callers still re-check `used` under the same query before trusting it.
     */
    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }
}
