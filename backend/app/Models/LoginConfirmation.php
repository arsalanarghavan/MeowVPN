<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LoginConfirmation extends Model
{
    public const EXPIRY_MINUTES = 5;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = ['user_id', 'session_token', 'ip_address', 'status', 'api_token', 'expires_at'];

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

    public function approve(): string
    {
        $token = $this->user->createToken('app-auth')->plainTextToken;
        $this->update([
            'status' => self::STATUS_APPROVED,
            'api_token' => $token,
        ]);
        return $token;
    }

    public function reject(): void
    {
        $this->update(['status' => self::STATUS_REJECTED]);
    }

    public static function createForUser(User $user, string $ipAddress): self
    {
        return self::create([
            'user_id' => $user->id,
            'session_token' => Str::random(48),
            'ip_address' => $ipAddress,
            'status' => self::STATUS_PENDING,
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ]);
    }
}
