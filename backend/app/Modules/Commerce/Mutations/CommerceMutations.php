<?php

namespace App\Modules\Commerce\Mutations;

use App\Models\DashboardUser;
use App\Models\SvpPlan;
use App\Models\SvpService;
use App\Modules\XuiPanel\Services\PlanInboundSyncService;
use App\Services\Commerce\ReceiptActionService;
use App\Services\Commerce\ReceiptProcessorService;
use App\Services\Commerce\ServiceProvisioner;
use App\Services\Commerce\ServiceProvisionService;
use App\Services\ResellerModuleGuard;
use App\Services\SettingsStore;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CommerceMutations
{
    public function __construct(
        protected ServiceProvisionService $provision,
        protected ServiceProvisioner $serviceProvisioner,
        protected ReceiptActionService $receipts,
        protected ReceiptProcessorService $receiptProcessor,
        protected SettingsStore $settings,
        protected ResellerModuleGuard $resellerModule,
        protected PlanInboundSyncService $planInboundSync,
    ) {}
    /** @return array<string, array{0: class-string, 1: string}> */
    public function handlers(): array
    {
        return [
            'plan' => [self::class, 'plan'],
            'plan_category' => [self::class, 'planCategory'],
            'card_add' => [self::class, 'cardAdd'],
            'card_update' => [self::class, 'cardUpdate'],
            'card_delete' => [self::class, 'cardDelete'],
            'card_reorder' => [self::class, 'cardReorder'],
            'receipt_set_status' => [self::class, 'receiptSetStatus'],
            'receipt_action' => [self::class, 'receiptAction'],
            'receipt_update' => [self::class, 'receiptUpdate'],
            'receipt_reject_reasons_save' => [self::class, 'receiptRejectReasonsSave'],
            'discount_save' => [self::class, 'discountSave'],
            'discount_delete' => [self::class, 'discountDelete'],
            'discount_redemptions' => [self::class, 'discountRedemptions'],
            'user_create_service' => [self::class, 'userCreateService'],
            'user_renew_service' => [self::class, 'userRenewService'],
            'user_add_volume' => [self::class, 'userAddVolume'],
            'user_reduce_volume' => [self::class, 'userReduceVolume'],
            'user_add_days' => [self::class, 'userAddDays'],
            'user_reduce_days' => [self::class, 'userReduceDays'],
            'service_delete' => [self::class, 'serviceDelete'],
            'service_set_note' => [self::class, 'serviceSetNote'],
            'user_service_toggle_enable' => [self::class, 'userServiceToggleEnable'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function plan(array $payload, ?Authenticatable $actor): array
    {
        $action = (string) ($payload['plan_action'] ?? '');
        $id = (int) ($payload['id'] ?? $payload['plan_id'] ?? 0);

        if ($action === 'delete' && $id > 0) {
            SvpPlan::query()->where('id', $id)->delete();

            return svp_ok(['plan_id' => $id]);
        }

        if ($action === 'toggle' && $id > 0) {
            $plan = SvpPlan::query()->find($id);
            if (! $plan) {
                return svp_err('not_found');
            }
            $plan->update(['active' => ! $plan->active]);

            return svp_ok(['plan_id' => $id]);
        }

        $data = collect($payload)->only([
            'name', 'category', 'duration_days', 'traffic_gb', 'price', 'pricing_type',
            'price_per_gb', 'traffic_gb_min', 'traffic_gb_max', 'clients_count', 'inbound_id',
            'panel_id', 'service_type', 'l2tp_server_id', 'active', 'sort_order',
            'owner_svp_user_id', 'wholesale_line_id', 'panel_template_id', 'quota_display_mode',
        ])->filter(fn ($v) => $v !== null)->all();

        if (isset($payload['plan_panel_id']) && ! isset($data['panel_id'])) {
            $data['panel_id'] = (int) $payload['plan_panel_id'];
        }
        if (isset($payload['plan_pricing_type']) && ! isset($data['pricing_type'])) {
            $data['pricing_type'] = (string) $payload['plan_pricing_type'];
        }
        if (array_key_exists('plan_active', $payload) && ! array_key_exists('active', $data)) {
            $data['active'] = ! empty($payload['plan_active']) ? 1 : 0;
        }
        if (array_key_exists('owner_svp_user_id', $data)) {
            $data['owner_svp_user_id'] = $this->resellerModule->normalizeOwnerId((int) $data['owner_svp_user_id']);
        }
        if (array_key_exists('wholesale_line_id', $data)) {
            $wl = (int) $data['wholesale_line_id'];
            $data['wholesale_line_id'] = $wl > 0 ? $wl : null;
        }
        if (array_key_exists('panel_template_id', $data)) {
            $tid = (int) $data['panel_template_id'];
            $data['panel_template_id'] = $tid > 0 ? $tid : null;
        }
        if (array_key_exists('quota_display_mode', $data)) {
            $qdm = strtolower(trim((string) $data['quota_display_mode']));
            $data['quota_display_mode'] = $qdm === 'hide_as_unlimited' ? 'hide_as_unlimited' : 'show';
        }

        if (isset($payload['inbound_ids']) && is_array($payload['inbound_ids'])) {
            $ids = [];
            foreach ($payload['inbound_ids'] as $raw) {
                $n = (int) $raw;
                if ($n > 0 && ! in_array($n, $ids, true)) {
                    $ids[] = $n;
                }
            }
            if ($ids !== []) {
                if (Schema::hasColumn('svp_plans', 'inbound_ids')) {
                    $data['inbound_ids'] = json_encode(array_values($ids));
                }
                if (! isset($data['inbound_id'])) {
                    $data['inbound_id'] = $ids[0];
                }
            }
        }

        $existing = $id > 0 ? SvpPlan::query()->find($id) : null;
        $oldInboundIds = $existing ? $this->planInboundIds($existing) : null;
        $isUpdate = $id > 0 && ($action === 'update' || $action === '');

        if ($id > 0) {
            if (! $existing) {
                return svp_err('not_found');
            }
            SvpPlan::query()->where('id', $id)->update($data);
            $planId = $id;
        } else {
            $plan = SvpPlan::query()->create(array_merge($data, ['created_at' => now()]));
            $planId = (int) $plan->id;
        }

        $response = ['plan_id' => $planId];
        if ($isUpdate && $existing) {
            $updated = SvpPlan::query()->find($planId);
            $syncMeta = $this->planInboundSync->propagatePlanInbounds(
                $planId,
                $oldInboundIds,
                $this->planInboundIds($updated),
            );
            if ($syncMeta !== []) {
                $response['inbound_sync'] = $syncMeta;
            }
        }

        return svp_ok($response);
    }

    /** @return array<int, int> */
    protected function planInboundIds(?object $plan): array
    {
        if (! $plan) {
            return [];
        }
        if (Schema::hasColumn('svp_plans', 'inbound_ids')) {
            $raw = trim((string) ($plan->inbound_ids ?? ''));
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $out = [];
                    foreach ($decoded as $value) {
                        $iid = (int) $value;
                        if ($iid > 0 && ! in_array($iid, $out, true)) {
                            $out[] = $iid;
                        }
                    }
                    if ($out !== []) {
                        return $out;
                    }
                }
            }
        }
        $single = (int) ($plan->inbound_id ?? 0);

        return $single > 0 ? [$single] : [];
    }

    /** @param  array<string, mixed>  $payload */
    public function planCategory(array $payload, ?Authenticatable $actor): array
    {
        $id = (int) ($payload['id'] ?? 0);
        $data = [
            'panel_id' => (int) ($payload['panel_id'] ?? 1),
            'slug' => (string) ($payload['slug'] ?? ''),
            'label' => (string) ($payload['label'] ?? ''),
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
            'active' => (bool) ($payload['active'] ?? true),
        ];

        if ($id > 0) {
            DB::table('svp_plan_categories')->where('id', $id)->update($data);

            return svp_ok(['id' => $id]);
        }

        $newId = DB::table('svp_plan_categories')->insertGetId(array_merge($data, ['created_at' => now()]));

        return svp_ok(['id' => $newId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function cardAdd(array $payload, ?Authenticatable $actor): array
    {
        $id = DB::table('svp_cards')->insertGetId([
            'owner_svp_user_id' => $this->resellerModule->normalizeOwnerId((int) ($payload['owner_svp_user_id'] ?? 0)),
            'card_number' => (string) ($payload['card_number'] ?? ''),
            'holder_name' => (string) ($payload['holder_name'] ?? ''),
            'bank_name' => (string) ($payload['bank_name'] ?? ''),
            'method_key' => (string) ($payload['method_key'] ?? 'c2c'),
            'daily_limit' => (float) ($payload['daily_limit'] ?? 0),
            'priority' => (int) ($payload['priority'] ?? 0),
            'note' => $payload['note'] ?? null,
            'active' => (bool) ($payload['active'] ?? true),
            'created_at' => now(),
        ]);

        return svp_ok(['card_id' => $id]);
    }

    /** @param  array<string, mixed>  $payload */
    public function cardUpdate(array $payload, ?Authenticatable $actor): array
    {
        $id = (int) ($payload['id'] ?? 0);
        $data = collect($payload)->only([
            'card_number', 'holder_name', 'bank_name', 'method_key', 'daily_limit',
            'priority', 'note', 'active',
        ])->filter(fn ($v) => $v !== null)->all();
        DB::table('svp_cards')->where('id', $id)->update($data);

        return svp_ok(['card_id' => $id]);
    }

    /** @param  array<string, mixed>  $payload */
    public function cardDelete(array $payload, ?Authenticatable $actor): array
    {
        $id = (int) ($payload['id'] ?? 0);
        DB::table('svp_cards')->where('id', $id)->delete();

        return svp_ok(['card_id' => $id]);
    }

    /** @param  array<string, mixed>  $payload */
    public function cardReorder(array $payload, ?Authenticatable $actor): array
    {
        foreach ((array) ($payload['order'] ?? []) as $priority => $cardId) {
            DB::table('svp_cards')->where('id', (int) $cardId)->update(['priority' => (int) $priority]);
        }

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function receiptSetStatus(array $payload, ?Authenticatable $actor): array
    {
        $id = (int) ($payload['receipt_id'] ?? $payload['id'] ?? 0);
        $status = (string) ($payload['status'] ?? '');
        $label = $actor?->username ?? ($actor?->email ?? 'admin');

        if ($status === 'approved') {
            return $this->receiptProcessor->approve($id, $label);
        }
        if ($status === 'rejected') {
            return $this->receiptProcessor->reject($id, $label, (string) ($payload['reject_reason'] ?? ''));
        }

        return svp_err('invalid_status');
    }

    /** @param  array<string, mixed>  $payload */
    public function receiptAction(array $payload, ?Authenticatable $actor): array
    {
        return $this->receipts->apply($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function receiptUpdate(array $payload, ?Authenticatable $actor): array
    {
        $id = (int) ($payload['id'] ?? $payload['receipt_id'] ?? 0);
        $data = collect($payload)->only(['amount', 'note'])->filter(fn ($v) => $v !== null)->all();
        DB::table('svp_receipts')->where('id', $id)->update($data);

        return svp_ok(['receipt_id' => $id]);
    }

    /** @param  array<string, mixed>  $payload */
    public function receiptRejectReasonsSave(array $payload, ?Authenticatable $actor): array
    {
        $reasons = $payload['reasons'] ?? $payload['reject_reasons'] ?? [];
        $this->settings->set('receipt_reject_reasons', is_array($reasons) ? $reasons : []);

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function discountSave(array $payload, ?Authenticatable $actor): array
    {
        $id = (int) ($payload['id'] ?? 0);
        $data = collect($payload)->only([
            'code', 'type', 'value', 'max_uses', 'per_user_limit', 'min_order_amount',
            'starts_at', 'ends_at', 'active', 'allowed_plan_ids', 'allow_new_purchase',
            'allow_renew', 'allow_add_volume', 'allow_add_slots', 'owner_svp_user_id',
        ])->filter(fn ($v) => $v !== null)->all();
        if (array_key_exists('owner_svp_user_id', $data)) {
            $data['owner_svp_user_id'] = $this->resellerModule->normalizeOwnerId((int) $data['owner_svp_user_id']);
        }
        if ($id > 0) {
            DB::table('svp_discount_codes')->where('id', $id)->update($data);

            return svp_ok(['id' => $id]);
        }
        $newId = DB::table('svp_discount_codes')->insertGetId(array_merge($data, ['created_at' => now()]));

        return svp_ok(['id' => $newId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function discountDelete(array $payload, ?Authenticatable $actor): array
    {
        DB::table('svp_discount_codes')->where('id', (int) ($payload['id'] ?? 0))->delete();

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function discountRedemptions(array $payload, ?Authenticatable $actor): array
    {
        $q = DB::table('svp_discount_redemptions')->orderByDesc('id');
        $codeId = (int) ($payload['code_id'] ?? 0);
        if ($codeId > 0) {
            $q->where('discount_code_id', $codeId);
        }
        if ($actor instanceof DashboardUser && $actor->role === 'reseller') {
            $ownerId = (int) ($actor->svp_user_id ?? 0);
            if ($ownerId < 1) {
                return svp_err('forbidden');
            }
            if ($codeId > 0) {
                $codeOwner = (int) DB::table('svp_discount_codes')->where('id', $codeId)->value('owner_svp_user_id');
                if ($codeOwner !== $ownerId) {
                    return svp_err('forbidden');
                }
            } else {
                $q->whereIn('discount_code_id', function ($sub) use ($ownerId) {
                    $sub->select('id')->from('svp_discount_codes')->where('owner_svp_user_id', $ownerId);
                });
            }
        }

        return svp_ok(['rows' => $q->limit(100)->get()]);
    }

    /** @param  array<string, mixed>  $payload */
    public function userCreateService(array $payload, ?Authenticatable $actor): array
    {
        $planId = (int) ($payload['plan_id'] ?? 0);
        $userId = (int) ($payload['user_id'] ?? 0);
        $mode = (string) ($payload['mode'] ?? '');
        if ($planId > 0 && $userId > 0 && $mode !== '') {
            $result = app(\App\Services\Commerce\AdminUserOpsService::class)->adminCreateService(
                $userId,
                $planId,
                isset($payload['volume_gb']) ? (int) $payload['volume_gb'] : null,
                $mode,
            );
            if (empty($result['ok'])) {
                return svp_err((string) ($result['reason'] ?? 'provision_failed'), $result);
            }

            return svp_ok($result);
        }
        if ($planId > 0 && $userId > 0) {
            $result = $this->serviceProvisioner->createFromPlan(
                $userId,
                $planId,
                isset($payload['volume_gb']) ? (int) $payload['volume_gb'] : null,
            );
            if (empty($result['ok'])) {
                return svp_err((string) ($result['reason'] ?? 'provision_failed'), $result);
            }

            return svp_ok(['service_id' => (int) ($result['service_id'] ?? 0)]);
        }

        $service = SvpService::query()->create([
            'user_id' => $userId,
            'panel_id' => (int) ($payload['panel_id'] ?? 1),
            'inbound_id' => (int) ($payload['inbound_id'] ?? 0),
            'email' => (string) ($payload['email'] ?? 'manual@local'),
            'plan_id' => $payload['plan_id'] ?? null,
            'provision_type' => 'manual',
            'created_at' => now(),
        ]);

        return svp_ok(['service_id' => $service->id]);
    }

    /** @param  array<string, mixed>  $payload */
    public function userRenewService(array $payload, ?Authenticatable $actor): array
    {
        return $this->provision->renew(
            (int) ($payload['service_id'] ?? 0),
            (string) ($payload['mode'] ?? 'free')
        );
    }

    /** @param  array<string, mixed>  $payload */
    public function userAddVolume(array $payload, ?Authenticatable $actor): array
    {
        return $this->provision->addVolume(
            (int) ($payload['service_id'] ?? 0),
            (int) ($payload['extra_gb'] ?? $payload['volume_gb'] ?? 0),
            (string) ($payload['mode'] ?? 'free')
        );
    }

    /** @param  array<string, mixed>  $payload */
    public function userReduceVolume(array $payload, ?Authenticatable $actor): array
    {
        return $this->provision->reduceVolume(
            (int) ($payload['service_id'] ?? 0),
            (int) ($payload['reduce_gb'] ?? $payload['extra_gb'] ?? 0)
        );
    }

    /** @param  array<string, mixed>  $payload */
    public function userAddDays(array $payload, ?Authenticatable $actor): array
    {
        return $this->provision->addDays(
            (int) ($payload['service_id'] ?? 0),
            (int) ($payload['days'] ?? 0)
        );
    }

    /** @param  array<string, mixed>  $payload */
    public function userReduceDays(array $payload, ?Authenticatable $actor): array
    {
        return $this->provision->reduceDays(
            (int) ($payload['service_id'] ?? 0),
            (int) ($payload['days'] ?? 0)
        );
    }

    /** @param  array<string, mixed>  $payload */
    public function serviceDelete(array $payload, ?Authenticatable $actor): array
    {
        SvpService::query()->where('id', (int) ($payload['service_id'] ?? 0))->update(['deleted_at' => now()]);

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function serviceSetNote(array $payload, ?Authenticatable $actor): array
    {
        SvpService::query()->where('id', (int) ($payload['service_id'] ?? 0))
            ->update(['service_note' => (string) ($payload['note'] ?? '')]);

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function userServiceToggleEnable(array $payload, ?Authenticatable $actor): array
    {
        return $this->provision->toggleEnable(
            (int) ($payload['service_id'] ?? 0),
            (bool) ($payload['enabled'] ?? $payload['enable'] ?? true)
        );
    }
}
