<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AppLoginToken extends Model
{
    public const EXPIRY_MINUTES = 5;

    protected $fillable = ['token', 'user_id', 'expires_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public static function createForUser(User $user): self
    {
        return self::create([
            'token' => Str::random(48),
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ]);
    }
}
