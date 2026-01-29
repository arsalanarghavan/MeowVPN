<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'flag_emoji',
        'ip_address',
        'api_domain',
        'admin_user',
        'admin_pass',
        'api_key',
        'capacity',
        'active_users_count',
        'type',
        'location_tag',
        'is_active',
        'panel_type',
    ];

    protected $hidden = [
        'admin_pass',
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'admin_pass' => 'encrypted',
            'api_key' => 'encrypted',
        ];
    }

    /**
     * Check if this server uses Hiddify panel
     */
    public function isHiddify(): bool
    {
        return $this->panel_type === 'hiddify';
    }

    /**
     * Check if this server uses Marzban panel
     */
    public function isMarzban(): bool
    {
        return $this->panel_type === 'marzban';
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscriptionLinks()
    {
        return $this->hasMany(SubscriptionLink::class);
    }

    public function hasCapacity(): bool
    {
        return $this->active_users_count < $this->capacity;
    }

    public function getAvailableSlots(): int
    {
        return max(0, $this->capacity - $this->active_users_count);
    }
}

