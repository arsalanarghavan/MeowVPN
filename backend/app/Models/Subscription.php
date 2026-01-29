<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'server_id',
        'uuid',
        'marzban_username',
        'panel_username',
        'status',
        'total_traffic',
        'used_traffic',
        'expire_date',
        'max_devices',
    ];

    protected function casts(): array
    {
        return [
            'expire_date' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($subscription) {
            if (empty($subscription->uuid)) {
                $subscription->uuid = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function subscriptionLinks()
    {
        return $this->hasMany(SubscriptionLink::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        if ($this->expire_date && $this->expire_date->isPast()) {
            return true;
        }

        if ($this->total_traffic > 0 && $this->used_traffic >= $this->total_traffic) {
            return true;
        }

        return false;
    }

    public function getRemainingTraffic(): int
    {
        if ($this->total_traffic === 0) {
            return -1; // Unlimited
        }
        return max(0, $this->total_traffic - $this->used_traffic);
    }

    public function getRemainingDays(): ?int
    {
        if (!$this->expire_date) {
            return null;
        }
        return max(0, now()->diffInDays($this->expire_date, false));
    }

    /**
     * Check if this is a multi-server subscription
     */
    public function isMultiServer(): bool
    {
        return $this->server_id === null;
    }

    /**
     * Get all servers associated with this subscription (for multi-server)
     */
    public function getServers()
    {
        if ($this->server_id) {
            return collect([$this->server]);
        }

        return $this->subscriptionLinks()
            ->with('server')
            ->get()
            ->pluck('server')
            ->unique('id')
            ->filter();
    }
}

