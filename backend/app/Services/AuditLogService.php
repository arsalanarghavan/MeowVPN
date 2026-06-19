<?php

namespace App\Services;

use App\Models\DashboardUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditLogService
{
    /** @var array<string, true> */
    protected static array $sensitiveOps = [
        'user_status' => true,
        'user_balance_delta' => true,
        'user_merge' => true,
        'user_manual_create' => true,
        'receipt_action' => true,
        'receipt_set_status' => true,
        'receipt_update' => true,
        'user_create_service' => true,
        'service_delete' => true,
        'reseller_permissions_save' => true,
        'bot_set_webhook' => true,
        'bot_delete_webhook' => true,
        'reseller_bot_webhook_set' => true,
        'reseller_bot_webhook_delete' => true,
        'telegram_relay_set_webhook' => true,
        'telegram_relay_rotate_secret' => true,
        'user_set_role' => true,
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $result
     */
    public function recordIfSensitive(string $op, array $payload, array $result, DashboardUser $actor): void
    {
        if (empty($result['ok']) || ! isset(self::$sensitiveOps[$op])) {
            return;
        }

        if (! Schema::hasTable('svp_audit_log')) {
            return;
        }

        $targetId = (int) ($payload['user_id'] ?? $payload['svp_user_id'] ?? $payload['service_id'] ?? $payload['receipt_id'] ?? $payload['id'] ?? 0);
        $targetType = match (true) {
            isset($payload['service_id']) => 'service',
            isset($payload['receipt_id']) || str_starts_with($op, 'receipt_') => 'receipt',
            default => 'user',
        };

        $insert = [
            'domain' => 'admin',
            'event_type' => $op,
            'actor_kind' => $actor->role === 'reseller' ? 'reseller' : 'admin',
            'actor_svp_user_id' => (int) ($actor->svp_user_id ?? 0),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reseller_scope_id' => (int) ($actor->svp_user_id ?? 0),
            'payload_json' => json_encode($this->redact($payload), JSON_UNESCAPED_UNICODE),
            'ip_hash' => '',
            'created_at' => now(),
        ];
        DB::table('svp_audit_log')->insert($insert);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function redact(array $payload): array
    {
        $out = [];
        foreach ($payload as $k => $v) {
            if (is_array($v)) {
                $out[$k] = $this->redact($v);
            } elseif ($this->isSensitiveKey((string) $k)) {
                $out[$k] = '[redacted]';
            } else {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    protected function isSensitiveKey(string $key): bool
    {
        $k = strtolower($key);
        foreach (['password', 'token', 'secret', 'api_key', 'panel_password', 'panel_api_token', 'authorization'] as $needle) {
            if (str_contains($k, $needle)) {
                return true;
            }
        }

        return false;
    }
}
