<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price_base',
        'duration_days',
        'traffic_bytes',
        'max_concurrent_users',
        'max_devices',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_base' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function calculatePrice(int $concurrentUsers = 1): float
    {
        $coefficient = 1.0;
        if ($concurrentUsers > 1) {
            $coefficient = 1.0 + (($concurrentUsers - 1) * 0.4); // 1.4, 1.8, 2.2, etc.
        }
        return (float) $this->price_base * $coefficient;
    }

    public function isUnlimitedTraffic(): bool
    {
        return $this->traffic_bytes === 0;
    }
}

