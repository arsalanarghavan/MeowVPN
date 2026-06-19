<?php

namespace App\Services;

use App\Models\DashboardUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserActivityLogService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function logUserEvent(int $subjectUserId, string $eventType, array $payload, DashboardUser $actor): void
    {
        if ($subjectUserId < 1 || ! Schema::hasTable('svp_user_activity')) {
            return;
        }

        DB::table('svp_user_activity')->insert([
            'user_id' => $subjectUserId,
            'channel' => 'rest',
            'actor_kind' => $actor->role === 'reseller' ? 'svp_user' : 'admin',
            'actor_svp_user_id' => (int) ($actor->svp_user_id ?? 0),
            'event_type' => $eventType,
            'payload_json' => json_encode(array_merge(['event' => $eventType], $payload), JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }
}
