<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    use HasFactory;

    public const REGION_IRAN = 'iran';
    public const REGION_FOREIGN = 'foreign';

    public const CATEGORY_TUNNEL_ENTRY = 'tunnel_entry';
    public const CATEGORY_TUNNEL_EXIT = 'tunnel_exit';
    public const CATEGORY_DIRECT = 'direct';

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
        'region',
        'server_category',
        'is_active',
        'is_central',
        'panel_type',
        'provider',
        'aeza_server_id',
        'aeza_order_id',
    ];

    protected $hidden = [
        'admin_pass',
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_central' => 'boolean',
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

    public function isRegionIran(): bool
    {
        return $this->region === self::REGION_IRAN;
    }

    public function isRegionForeign(): bool
    {
        return $this->region === self::REGION_FOREIGN;
    }

    public function isTunnelEntry(): bool
    {
        return $this->server_category === self::CATEGORY_TUNNEL_ENTRY;
    }

    public function isTunnelExit(): bool
    {
        return $this->server_category === self::CATEGORY_TUNNEL_EXIT;
    }

    public function isDirect(): bool
    {
        return $this->server_category === self::CATEGORY_DIRECT;
    }

    public function isCentral(): bool
    {
        return (bool) ($this->is_central ?? false);
    }

    /**
     * Validate region + server_category combination (tunnel_entry=iran only, etc.)
     */
    public static function validateRegionCategory(string $region, string $serverCategory): bool
    {
        if ($serverCategory === self::CATEGORY_TUNNEL_ENTRY) {
            return $region === self::REGION_IRAN;
        }
        if (in_array($serverCategory, [self::CATEGORY_TUNNEL_EXIT, self::CATEGORY_DIRECT], true)) {
            return $region === self::REGION_FOREIGN;
        }
        return true;
    }
}

