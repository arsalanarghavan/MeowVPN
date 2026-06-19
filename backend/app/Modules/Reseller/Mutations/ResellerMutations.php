<?php

namespace App\Modules\Reseller\Mutations;

use App\Models\DashboardUser;
use App\Models\SvpUser;
use App\Modules\Reseller\Services\ResellerBackfillService;
use App\Modules\Reseller\Services\ResellerBotProfileService;
use App\Modules\Reseller\Services\ResellerClosureService;
use App\Modules\Reseller\Services\ResellerScopeService;
use App\Modules\Reseller\Services\ResellerWebhookService;
use App\Modules\Reseller\Services\WholesalePricingService;
use App\Services\Commerce\ServiceProvisionService;
use App\Services\Commerce\ServiceTransferService;
use App\Services\ResellerDefaultsService;
use App\Services\SettingsStore;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ResellerMutations
{
    public function __construct(
        protected WholesalePricingService $wholesale,
        protected ServiceTransferService $transfer,
        protected SettingsStore $settings,
        protected ResellerBotProfileService $botProfiles,
        protected ResellerWebhookService $webhooks,
        protected ResellerClosureService $closure,
        protected ResellerBackfillService $backfill,
        protected ResellerScopeService $scope,
        protected ServiceProvisionService $provision,
    ) {}

    /** @return array<string, array{0: class-string, 1: string}> */
    public function handlers(): array
    {
        return [
            'reseller_payment_methods_save' => [self::class, 'resellerPaymentMethodsSave'],
            'reseller_wallet_topup_checkout' => [self::class, 'resellerWalletTopupCheckout'],
            'reseller_dashboard_provision' => [self::class, 'resellerWpProvision'],
            'reseller_panel_prices_save' => [self::class, 'resellerPanelPricesSave'],
            'wholesale_line_save' => [self::class, 'wholesaleLineSave'],
            'wholesale_line_delete' => [self::class, 'wholesaleLineDelete'],
            'reseller_wholesale_lines_assign' => [self::class, 'resellerWholesaleLinesAssign'],
            'reseller_permissions_save' => [self::class, 'resellerPermissionsSave'],
            'reseller_bot_tokens_save' => [self::class, 'resellerBotTokensSave'],
            'reseller_bot_webhook_set' => [self::class, 'resellerBotWebhookSet'],
            'reseller_bind_users' => [self::class, 'resellerBindUsers'],
            'reseller_backfill_run' => [self::class, 'resellerBackfillRun'],
            'bot_reseller_toggle_enabled' => [self::class, 'botResellerToggleEnabled'],
            'bot_reseller_secret_rotate' => [self::class, 'botResellerSecretRotate'],
            'bot_reseller_delete' => [self::class, 'botResellerDelete'],
            'bot_reseller_save' => [self::class, 'botResellerSave'],
            'reseller_inbound_labels_save' => [self::class, 'resellerInboundLabelsSave'],
            'reseller_bot_webhook_delete' => [self::class, 'resellerBotWebhookDelete'],
            'user_service_add_slots' => [self::class, 'userServiceAddSlots'],
            'user_service_reduce_slots' => [self::class, 'userServiceReduceSlots'],
            'user_service_transfer' => [self::class, 'userServiceTransfer'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function resellerPaymentMethodsSave(array $payload, ?Authenticatable $actor): array
    {
        $resellerId = (int) ($payload['reseller_svp_user_id'] ?? $actor?->svp_user_id ?? 0);
        $methods = $payload['methods'] ?? $payload;
        $this->settings->set("reseller_payment_methods.{$resellerId}", is_array($methods) ? $methods : []);

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function resellerWalletTopupCheckout(array $payload, ?Authenticatable $actor): array
    {
        $amount = (float) ($payload['amount'] ?? 0);
        if ($amount <= 0) {
            return svp_err('invalid');
        }
        $txId = DB::table('svp_transactions')->insertGetId([
            'user_id' => (int) ($actor?->svp_user_id ?? 0),
            'amount' => $amount,
            'type' => 'reseller_wallet_topup',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        return svp_ok(['transaction_id' => $txId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function resellerWpProvision(array $payload, ?Authenticatable $actor): array
    {
        $username = (string) ($payload['username'] ?? '');
        if ($username === '') {
            return svp_err('invalid');
        }

        $parentId = (int) ($payload['parent_svp_user_id'] ?? $payload['invited_by'] ?? $actor?->svp_user_id ?? 0);
        $svpUserId = (int) ($payload['svp_user_id'] ?? 0);

        if ($svpUserId < 1) {
            $svpUser = SvpUser::query()->create([
                'username' => $username,
                'first_name' => (string) ($payload['first_name'] ?? ''),
                'last_name' => (string) ($payload['last_name'] ?? ''),
                'role' => 'reseller',
                'status' => 'approved',
                'invited_by' => $parentId > 0 ? $parentId : null,
                'approved_at' => now(),
                'approved_by' => $actor?->username ?? 'system',
                'created_at' => now(),
            ]);
            $svpUserId = (int) $svpUser->id;
        } else {
            SvpUser::query()->where('id', $svpUserId)->update([
                'username' => $username,
                'role' => 'reseller',
                'status' => 'approved',
                'invited_by' => $parentId > 0 ? $parentId : null,
                'approved_at' => now(),
                'approved_by' => $actor?->username ?? 'system',
            ]);
        }

        DashboardUser::query()->updateOrCreate(
            ['username' => $username],
            [
                'password' => bcrypt((string) ($payload['password'] ?? Str::random(12))),
                'role' => 'reseller',
                'svp_user_id' => $svpUserId,
                'permissions_json' => is_array($payload['permissions'] ?? null)
                    ? $payload['permissions']
                    : app(ResellerDefaultsService::class)->permissions(),
            ]
        );

        $this->closure->rebuildForUser($svpUserId);
        $this->botProfiles->ensureProfile($svpUserId);

        return svp_ok(['svp_user_id' => $svpUserId, 'username' => $username]);
    }

    /** @param  array<string, mixed>  $payload */
    public function resellerPanelPricesSave(array $payload, ?Authenticatable $actor): array
    {
        $resellerId = (int) ($payload['reseller_svp_user_id'] ?? $actor?->svp_user_id ?? 0);
        $parentId = (int) ($payload['parent_svp_user_id'] ?? $actor?->svp_user_id ?? 0);
        $prices = (array) ($payload['prices'] ?? []);

        foreach ($prices as $row) {
            if (! is_array($row)) {
                continue;
            }
            $panelId = (int) ($row['panel_id'] ?? 0);
            $price = (float) ($row['price'] ?? $row['price_per_gb'] ?? 0);

            if ($parentId > 0 && $panelId > 0) {
                $floor = $this->wholesale->validatePanelFloor($parentId, $resellerId, $panelId, $price);
                if (empty($floor['ok'])) {
                    return $floor;
                }
            }

            $access = $row['panel_access'] ?? $row['active'] ?? true;
            DB::table('svp_reseller_panel_prices')->updateOrInsert(
                [
                    'reseller_svp_user_id' => $resellerId,
                    'panel_id' => $panelId,
                ],
                [
                    'price_per_gb' => $price,
                    'panel_access' => (bool) $access,
                    'updated_at' => now(),
                ]
            );
        }

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function wholesaleLineSave(array $payload, ?Authenticatable $actor): array
    {
        return $this->wholesale->saveLine($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function wholesaleLineDelete(array $payload, ?Authenticatable $actor): array
    {
        return $this->wholesale->deleteLine((int) ($payload['id'] ?? 0));
    }

    /** @param  array<string, mixed>  $payload */
    public function resellerWholesaleLinesAssign(array $payload, ?Authenticatable $actor): array
    {
        return $this->wholesale->assignLines($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function resellerPermissionsSave(array $payload, ?Authenticatable $actor): array
    {
        $svpUserId = (int) ($payload['svp_user_id'] ?? 0);
        $perms = $payload['permissions'] ?? [];
        if ($svpUserId < 1) {
            return svp_err('invalid');
        }

        if ($actor instanceof DashboardUser && $actor->role === 'reseller') {
            $actorId = (int) ($actor->svp_user_id ?? 0);
            $target = SvpUser::query()->find($svpUserId);
            if (! $target || (string) $target->role !== 'reseller') {
                return svp_err('forbidden');
            }
            if ($svpUserId !== $actorId && ! $this->closure->isDescendantOf($actorId, $svpUserId)) {
                return svp_err('forbidden');
            }
        }

        $allowedKeys = app(ResellerDefaultsService::class)->permissionKeys();
        $filtered = [];
        if (is_array($perms)) {
            foreach ($allowedKeys as $key) {
                if (array_key_exists($key, $perms)) {
                    $filtered[$key] = (bool) $perms[$key];
                }
            }
        }
        if ($actor instanceof DashboardUser && $actor->role === 'reseller') {
            $actorPerms = is_array($actor->permissions_json) ? $actor->permissions_json : [];
            foreach ($filtered as $key => $enabled) {
                if ($enabled && empty($actorPerms[$key])) {
                    $filtered[$key] = false;
                }
            }
        }

        DashboardUser::query()
            ->where('svp_user_id', $svpUserId)
            ->update(['permissions_json' => $filtered]);

        return svp_ok(['svp_user_id' => $svpUserId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function resellerBotTokensSave(array $payload, ?Authenticatable $actor): array
    {
        $resellerId = (int) ($payload['reseller_svp_user_id'] ?? $actor?->svp_user_id ?? 0);
        if ($resellerId < 1) {
            return svp_err('invalid');
        }

        if (isset($payload['telegram_token'])) {
            $this->botProfiles->setToken($resellerId, 'telegram', (string) $payload['telegram_token']);
        }
        if (isset($payload['bale_token'])) {
            $this->botProfiles->setToken($resellerId, 'bale', (string) $payload['bale_token']);
        }

        return svp_ok(['reseller_svp_user_id' => $resellerId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function resellerBotWebhookSet(array $payload, ?Authenticatable $actor): array
    {
        $resellerId = (int) ($payload['reseller_svp_user_id'] ?? $actor?->svp_user_id ?? 0);
        $platform = (string) ($payload['platform'] ?? 'telegram');
        if ($resellerId < 1) {
            return svp_err('invalid');
        }

        return $this->webhooks->setWebhook($resellerId, $platform);
    }

    /** @param  array<string, mixed>  $payload */
    public function resellerBindUsers(array $payload, ?Authenticatable $actor): array
    {
        $resellerId = (int) ($payload['reseller_svp_user_id'] ?? 0);
        $userIds = (array) ($payload['user_ids'] ?? []);
        if ($resellerId < 1) {
            return svp_err('invalid');
        }

        foreach ($userIds as $uid) {
            $userId = (int) $uid;
            if ($userId < 1) {
                continue;
            }
            $user = SvpUser::query()->find($userId);
            if (! $user) {
                continue;
            }
            $oldParent = (int) ($user->invited_by ?? 0);
            $user->signup_reseller_svp_id = $resellerId;
            if ($oldParent < 1) {
                $user->invited_by = $resellerId;
            }
            $user->save();
            $this->closure->onInvitedByChanged($userId, $oldParent, (int) ($user->invited_by ?? 0));
        }

        return svp_ok(['bound' => count($userIds)]);
    }

    /** @param  array<string, mixed>  $payload */
    public function resellerBackfillRun(array $payload, ?Authenticatable $actor): array
    {
        return svp_ok($this->backfill->runBatch($payload));
    }

    /** @param  array<string, mixed>  $payload */
    public function botResellerToggleEnabled(array $payload, ?Authenticatable $actor): array
    {
        $resellerId = $this->resolveProfileResellerId($payload);
        if ($resellerId < 1) {
            return svp_err('invalid');
        }

        $this->botProfiles->saveProfile($resellerId, [
            'enabled' => (bool) ($payload['enabled'] ?? true),
        ]);

        return svp_ok(['reseller_svp_user_id' => $resellerId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function botResellerSecretRotate(array $payload, ?Authenticatable $actor): array
    {
        $resellerId = $this->resolveProfileResellerId($payload);
        if ($resellerId < 1) {
            return svp_err('invalid');
        }

        $secret = $this->botProfiles->rotateWebhookSecret($resellerId);
        $masked = strlen($secret) <= 4
            ? '****'
            : str_repeat('*', max(4, strlen($secret) - 4)).substr($secret, -4);

        return svp_ok(['secret' => $masked, 'webhook_secret' => $masked, 'secret_rotated' => true]);
    }

    /** @param  array<string, mixed>  $payload */
    public function botResellerDelete(array $payload, ?Authenticatable $actor): array
    {
        $resellerId = $this->resolveProfileResellerId($payload);
        if ($resellerId < 1 || ! Schema::hasTable('svp_reseller_bot_profiles')) {
            return svp_err('invalid');
        }

        DB::table('svp_reseller_bot_profiles')
            ->where('reseller_svp_user_id', $resellerId)
            ->delete();

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function botResellerSave(array $payload, ?Authenticatable $actor): array
    {
        $resellerId = (int) ($payload['reseller_svp_user_id'] ?? 0);
        if ($resellerId < 1) {
            $profileId = (int) ($payload['id'] ?? 0);
            if ($profileId > 0 && Schema::hasTable('svp_reseller_bot_profiles')) {
                $row = DB::table('svp_reseller_bot_profiles')->where('id', $profileId)->first();
                $resellerId = (int) ($row->reseller_svp_user_id ?? 0);
            }
        }
        if ($resellerId < 1) {
            return svp_err('invalid');
        }

        if (isset($payload['telegram_token'])) {
            $this->botProfiles->setToken($resellerId, 'telegram', (string) $payload['telegram_token']);
        }
        if (isset($payload['bale_token'])) {
            $this->botProfiles->setToken($resellerId, 'bale', (string) $payload['bale_token']);
        }

        $profile = $this->botProfiles->saveProfile($resellerId, $payload);

        return svp_ok(['id' => (int) $profile->id, 'reseller_svp_user_id' => $resellerId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function resellerInboundLabelsSave(array $payload, ?Authenticatable $actor): array
    {
        $resellerId = (int) ($payload['reseller_svp_user_id'] ?? $actor?->svp_user_id ?? 0);
        $labels = $payload['labels'] ?? [];
        if ($resellerId < 1 || ! Schema::hasTable('svp_reseller_inbound_display_names')) {
            return svp_err('invalid');
        }

        if (! is_array($labels)) {
            return svp_ok();
        }

        foreach ($labels as $row) {
            if (! is_array($row)) {
                continue;
            }
            $panelId = (int) ($row['panel_id'] ?? 0);
            $inboundId = (int) ($row['inbound_id'] ?? 0);
            $label = (string) ($row['label'] ?? '');
            if ($panelId < 1 || $inboundId < 1) {
                continue;
            }
            DB::table('svp_reseller_inbound_display_names')->updateOrInsert(
                [
                    'reseller_svp_user_id' => $resellerId,
                    'panel_id' => $panelId,
                    'inbound_id' => $inboundId,
                ],
                ['label' => $label]
            );
        }

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function resellerBotWebhookDelete(array $payload, ?Authenticatable $actor): array
    {
        $resellerId = (int) ($payload['reseller_svp_user_id'] ?? $actor?->svp_user_id ?? 0);
        $platform = (string) ($payload['platform'] ?? 'telegram');
        if ($resellerId < 1) {
            return svp_err('invalid');
        }

        return $this->webhooks->deleteWebhook($resellerId, $platform);
    }

    /** @param  array<string, mixed>  $payload */
    public function userServiceAddSlots(array $payload, ?Authenticatable $actor): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        $slots = (int) ($payload['slots'] ?? 1);
        if ($serviceId < 1) {
            return svp_err('invalid');
        }

        return $this->provision->addSlots($serviceId, $slots, 'free');
    }

    /** @param  array<string, mixed>  $payload */
    public function userServiceReduceSlots(array $payload, ?Authenticatable $actor): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        $slots = (int) ($payload['slots'] ?? 1);
        if ($serviceId < 1) {
            return svp_err('invalid');
        }

        return $this->provision->reduceSlots($serviceId, $slots);
    }

    /** @param  array<string, mixed>  $payload */
    public function userServiceTransfer(array $payload, ?Authenticatable $actor): array
    {
        return $this->transfer->transfer(
            (int) ($payload['service_id'] ?? 0),
            (string) ($payload['target'] ?? '')
        );
    }

    /** @param  array<string, mixed>  $payload */
    protected function resolveProfileResellerId(array $payload): int
    {
        $resellerId = (int) ($payload['reseller_svp_user_id'] ?? 0);
        if ($resellerId > 0) {
            return $resellerId;
        }

        $id = (int) ($payload['id'] ?? 0);
        if ($id < 1 || ! Schema::hasTable('svp_reseller_bot_profiles')) {
            return 0;
        }

        $row = DB::table('svp_reseller_bot_profiles')->where('id', $id)->first();

        return (int) ($row->reseller_svp_user_id ?? 0);
    }
}
