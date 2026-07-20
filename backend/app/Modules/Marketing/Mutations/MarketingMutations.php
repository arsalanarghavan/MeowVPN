<?php

namespace App\Modules\Marketing\Mutations;

use App\Models\DashboardUser;
use App\Modules\Marketing\Jobs\BroadcastWorkerJob;
use App\Modules\Marketing\Services\BroadcastQueueService;
use App\Modules\Marketing\Services\BroadcastWorkerService;
use App\Modules\Marketing\Services\MarketingAutomationService;
use App\Services\ResellerModuleGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

class MarketingMutations
{
    public function __construct(
        protected BroadcastQueueService $broadcastQueue,
        protected BroadcastWorkerService $broadcastWorker,
        protected MarketingAutomationService $marketing,
        protected ResellerModuleGuard $resellerModule,
    ) {}

    /** @return array<string, array{0: class-string, 1: string}> */
    public function handlers(): array
    {
        return [
            'broadcast_send' => [self::class, 'broadcastSend'],
            'broadcast_cancel' => [self::class, 'broadcastCancel'],
            'broadcast_run_worker' => [self::class, 'broadcastRunWorker'],
            'marketing_rule_save' => [self::class, 'marketingRuleSave'],
            'marketing_rule_delete' => [self::class, 'marketingRuleDelete'],
            'marketing_send_manual' => [self::class, 'marketingSendManual'],
            'marketing_run_rule_now' => [self::class, 'marketingRunRuleNow'],
            'marketing_lifecycle_confirm_defaults' => [self::class, 'marketingLifecycleConfirmDefaults'],
            'marketing_preview_message' => [self::class, 'marketingPreviewMessage'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function broadcastSend(array $payload, ?Authenticatable $actor): array
    {
        if (! $actor instanceof DashboardUser) {
            return svp_err('forbidden');
        }

        return $this->broadcastQueue->createAndEnqueue($payload, $actor);
    }

    /** @param  array<string, mixed>  $payload */
    public function broadcastCancel(array $payload, ?Authenticatable $actor): array
    {
        $actorOwner = $actor instanceof DashboardUser && $actor->role === 'reseller'
            ? (int) ($actor->svp_user_id ?? 0)
            : 0;

        return $this->broadcastQueue->cancelBroadcast((int) ($payload['id'] ?? 0), $actorOwner);
    }

    /** @param  array<string, mixed>  $payload */
    public function broadcastRunWorker(array $payload, ?Authenticatable $actor): array
    {
        $iterations = max(1, min(80, (int) ($payload['max_iterations'] ?? 30)));
        for ($i = 0; $i < $iterations; ++$i) {
            BroadcastWorkerJob::dispatchSync();
        }

        return svp_ok(['iterations' => $iterations]);
    }

    /** @param  array<string, mixed>  $payload */
    public function marketingRuleSave(array $payload, ?Authenticatable $actor): array
    {
        $allowedSegments = ['never_purchased', 'abandoned_checkout', 'expiring_renew', 'churned', 'stale_buy_funnel'];
        $segment = (string) ($payload['segment_key'] ?? '');
        if ($segment !== '' && ! in_array($segment, $allowedSegments, true)) {
            return svp_err('invalid_segment');
        }

        $id = (int) ($payload['id'] ?? 0);
        $data = collect($payload)->only([
            'segment_key', 'enabled', 'priority', 'cooldown_days', 'after_days', 'pending_hours',
            'funnel_idle_hours', 'expires_within_days', 'discount_type', 'discount_value',
            'max_discount_toman', 'code_valid_days', 'max_uses_per_user', 'message_body',
            'channel_telegram', 'channel_bale',
        ])->filter(fn ($v) => $v !== null)->all();
        if ($id > 0) {
            $rule = DB::table('svp_marketing_rules')->where('id', $id)->first();
            if (! $rule) {
                return svp_err('not_found');
            }
            if ($actor instanceof DashboardUser && $actor->role === 'reseller') {
                if ((int) $rule->owner_svp_user_id !== (int) ($actor->svp_user_id ?? 0)) {
                    return svp_err('forbidden');
                }
            }
            DB::table('svp_marketing_rules')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));

            return svp_ok(['id' => $id]);
        }

        $owner = $actor instanceof DashboardUser && $actor->role === 'reseller'
            ? (int) ($actor->svp_user_id ?? 0)
            : $this->resellerModule->normalizeOwnerId((int) ($payload['owner_svp_user_id'] ?? 0));

        $newId = DB::table('svp_marketing_rules')->insertGetId(array_merge($data, [
            'owner_svp_user_id' => $owner,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        return svp_ok(['id' => $newId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function marketingRuleDelete(array $payload, ?Authenticatable $actor): array
    {
        $id = (int) ($payload['id'] ?? 0);
        if ($actor instanceof DashboardUser && $actor->role === 'reseller') {
            $rule = DB::table('svp_marketing_rules')->where('id', $id)->first();
            if ($rule && (int) $rule->owner_svp_user_id !== (int) ($actor->svp_user_id ?? 0)) {
                return svp_err('forbidden');
            }
        }
        DB::table('svp_marketing_rules')->where('id', $id)->delete();

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function marketingSendManual(array $payload, ?Authenticatable $actor): array
    {
        $actorOwner = $actor instanceof DashboardUser && $actor->role === 'reseller'
            ? (int) ($actor->svp_user_id ?? 0)
            : (int) ($payload['owner_svp_user_id'] ?? 0);

        $res = $this->marketing->sendManual(
            (int) ($payload['user_id'] ?? 0),
            (int) ($payload['rule_id'] ?? 0),
            $actorOwner,
        );

        return ! empty($res['ok']) ? svp_ok($res) : svp_err((string) ($res['message'] ?? 'failed'));
    }

    /** @param  array<string, mixed>  $payload */
    public function marketingRunRuleNow(array $payload, ?Authenticatable $actor): array
    {
        $ruleId = (int) ($payload['rule_id'] ?? $payload['id'] ?? 0);
        if ($actor instanceof DashboardUser && $actor->role === 'reseller') {
            $rule = DB::table('svp_marketing_rules')->where('id', $ruleId)->first();
            if ($rule && (int) $rule->owner_svp_user_id !== (int) ($actor->svp_user_id ?? 0)) {
                return svp_err('forbidden');
            }
        }

        $stats = $this->marketing->runRuleNow($ruleId, (int) ($payload['limit'] ?? 80));

        return svp_ok($stats);
    }

    /** @param  array<string, mixed>  $payload */
    public function marketingLifecycleConfirmDefaults(array $payload, ?Authenticatable $actor): array
    {
        if (! $actor instanceof DashboardUser || $actor->role !== 'admin') {
            return svp_err('forbidden');
        }

        $enabled = $this->marketing->confirmLifecycleDefaults();

        return svp_ok([
            'enabled_rules' => $enabled,
            'lifecycle_confirmed' => true,
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    public function marketingPreviewMessage(array $payload, ?Authenticatable $actor): array
    {
        $ruleId = (int) ($payload['rule_id'] ?? 0);
        $userId = (int) ($payload['svp_user_id'] ?? $payload['user_id'] ?? 0);
        if ($ruleId < 1) {
            return svp_err('invalid_rule');
        }

        $rule = DB::table('svp_marketing_rules')->where('id', $ruleId)->first();
        if (! $rule) {
            return svp_err('not_found');
        }

        if ($actor instanceof DashboardUser && $actor->role === 'reseller') {
            if ((int) ($rule->owner_svp_user_id ?? 0) !== (int) ($actor->svp_user_id ?? 0)) {
                return svp_err('forbidden_scope');
            }
        }

        $res = $this->marketing->previewMessage($ruleId, $userId);

        if (empty($res['ok'])) {
            return svp_err((string) ($res['message'] ?? 'failed'));
        }

        return [
            'ok' => true,
            'message' => (string) ($res['message'] ?? ''),
            'code' => (string) ($res['code'] ?? 'PREVIEW-CODE'),
        ];
    }
}
