<?php

namespace App\Modules\Commerce\Mutations;

use App\Models\DashboardUser;
use App\Models\SvpPlan;
use App\Models\SvpService;
use App\Modules\Commerce\Services\PlanResellerRulesService;
use App\Modules\PasarGuard\Services\PanelClientFactory;
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
        protected PlanResellerRulesService $planResellerRules,
        protected PanelClientFactory $panelFactory,
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
        $resellerActor = (int) ($payload['__actor_svp_user_id'] ?? 0);

        if ($action === 'delete' && $id > 0) {
            $existing = SvpPlan::query()->find($id);
            if ($resellerActor > 0) {
                if (! $existing || ! $this->planResellerRules->resellerOwnsPlan($resellerActor, $existing)) {
                    return svp_err('forbidden', ['code' => 'forbidden']);
                }
                if ((string) ($existing->service_type ?? '') === 'l2tp'
                    && ! $this->planResellerRules->resellerL2tpAllowedOnPanel(
                        $resellerActor,
                        max(1, (int) ($existing->panel_id ?? 1))
                    )) {
                    return svp_err('l2tp_forbidden_for_reseller', ['code' => 'l2tp_forbidden_for_reseller']);
                }
            }
            SvpPlan::query()->where('id', $id)->delete();

            return svp_ok(['plan_id' => $id]);
        }

        if ($action === 'toggle' && $id > 0) {
            $plan = SvpPlan::query()->find($id);
            if (! $plan) {
                return svp_err('not_found');
            }
            if ($resellerActor > 0) {
                if (! $this->planResellerRules->resellerOwnsPlan($resellerActor, $plan)) {
                    return svp_err('forbidden', ['code' => 'forbidden']);
                }
                if ((string) ($plan->service_type ?? '') === 'l2tp'
                    && ! $this->planResellerRules->resellerL2tpAllowedOnPanel(
                        $resellerActor,
                        max(1, (int) ($plan->panel_id ?? 1))
                    )) {
                    return svp_err('l2tp_forbidden_for_reseller', ['code' => 'l2tp_forbidden_for_reseller']);
                }
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
            $data['quota_display_mode'] = in_array($qdm, ['hide_as_unlimited', 'hide'], true)
                ? 'hide_as_unlimited'
                : 'show';
        }

        if (array_key_exists('inbound_ids', $payload) && is_array($payload['inbound_ids'])) {
            $ids = [];
            foreach ($payload['inbound_ids'] as $raw) {
                $n = (int) $raw;
                if ($n > 0 && ! in_array($n, $ids, true)) {
                    $ids[] = $n;
                }
            }
            if (Schema::hasColumn('svp_plans', 'inbound_ids')) {
                $data['inbound_ids'] = $ids !== [] ? json_encode(array_values($ids)) : null;
            }
            if ($ids !== []) {
                if (! isset($data['inbound_id'])) {
                    $data['inbound_id'] = $ids[0];
                }
            } else {
                $data['inbound_id'] = 0;
            }
        }

        $existing = $id > 0 ? SvpPlan::query()->find($id) : null;
        $oldInboundIds = $existing ? $this->planInboundIds($existing) : null;
        $isUpdate = $id > 0 && ($action === 'update' || $action === '');

        if ($id > 0 && ! $existing) {
            return svp_err('not_found');
        }

        if ($resellerActor > 0) {
            if ($existing && (string) ($existing->service_type ?? '') === 'l2tp'
                && ! $this->planResellerRules->resellerL2tpAllowedOnPanel(
                    $resellerActor,
                    max(1, (int) ($existing->panel_id ?? 1))
                )) {
                return svp_err('l2tp_forbidden_for_reseller', [
                    'code' => 'l2tp_forbidden_for_reseller',
                    'plan_id' => $id,
                ]);
            }

            $fr = $this->planResellerRules->apply($resellerActor, $data, $existing);
            if (! empty($fr['block'])) {
                return svp_err((string) ($fr['code'] ?? 'forbidden'), [
                    'code' => (string) ($fr['code'] ?? 'forbidden'),
                    'plan_id' => $id > 0 ? $id : null,
                ]);
            }
            $data = $fr['row'];
        }

        if ($id > 0) {
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
        $resellerActor = (int) ($payload['__actor_svp_user_id'] ?? 0);
        $id = (int) ($payload['id'] ?? $payload['pc_id'] ?? $payload['category_id'] ?? 0);
        $action = strtolower(trim((string) ($payload['pc_action'] ?? $payload['action'] ?? '')));

        if ($action === 'delete' && $id > 0) {
            $existing = DB::table('svp_plan_categories')->where('id', $id)->first();
            if (! $existing) {
                return svp_err('not_found', ['code' => 'not_found']);
            }
            if ($resellerActor > 0 && ! $this->planResellerRules->resellerMayUsePanelCatalog(
                $resellerActor,
                max(1, (int) ($existing->panel_id ?? 1))
            )) {
                return svp_err('forbidden', ['code' => 'panel_not_allowed']);
            }
            $slug = (string) ($existing->slug ?? '');
            $panelId = max(1, (int) ($existing->panel_id ?? 1));
            if ($slug !== '' && Schema::hasTable('svp_plans')
                && DB::table('svp_plans')->where('panel_id', $panelId)->where('category', $slug)->exists()) {
                return svp_err('inuse', ['code' => 'inuse']);
            }
            DB::table('svp_plan_categories')->where('id', $id)->delete();

            return svp_ok(['id' => $id, 'code' => 'deleted']);
        }

        if ($action === 'toggle' && $id > 0) {
            $existing = DB::table('svp_plan_categories')->where('id', $id)->first();
            if (! $existing) {
                return svp_err('not_found', ['code' => 'not_found']);
            }
            if ($resellerActor > 0 && ! $this->planResellerRules->resellerMayUsePanelCatalog(
                $resellerActor,
                max(1, (int) ($existing->panel_id ?? 1))
            )) {
                return svp_err('forbidden', ['code' => 'panel_not_allowed']);
            }
            $next = empty($existing->active) ? 1 : 0;
            DB::table('svp_plan_categories')->where('id', $id)->update(['active' => $next]);

            return svp_ok(['id' => $id, 'active' => $next, 'code' => 'toggled']);
        }

        // WP aliases
        if (array_key_exists('pc_label', $payload) && ! array_key_exists('label', $payload)) {
            $payload['label'] = $payload['pc_label'];
        }
        if (array_key_exists('pc_slug', $payload) && ! array_key_exists('slug', $payload)) {
            $payload['slug'] = $payload['pc_slug'];
        }
        if (array_key_exists('pc_panel_id', $payload) && ! array_key_exists('panel_id', $payload)) {
            $payload['panel_id'] = $payload['pc_panel_id'];
        }
        if (array_key_exists('pc_sort', $payload) && ! array_key_exists('sort_order', $payload)) {
            $payload['sort_order'] = $payload['pc_sort'];
        }
        if (array_key_exists('pc_active', $payload) && ! array_key_exists('active', $payload)) {
            $payload['active'] = $payload['pc_active'];
        }
        foreach (['pc_buy_plan_intro_fa' => 'buy_plan_intro_fa', 'pc_buy_plan_intro_en' => 'buy_plan_intro_en'] as $from => $to) {
            if (array_key_exists($from, $payload) && ! array_key_exists($to, $payload)) {
                $payload[$to] = $payload[$from];
            }
        }

        $panelId = max(1, (int) ($payload['panel_id'] ?? 1));
        if ($resellerActor > 0 && ! $this->planResellerRules->resellerMayUsePanelCatalog($resellerActor, $panelId)) {
            return svp_err('forbidden', ['code' => 'panel_not_allowed']);
        }

        $data = [];
        if (array_key_exists('label', $payload)) {
            $data['label'] = (string) $payload['label'];
        }
        if (array_key_exists('sort_order', $payload)) {
            $data['sort_order'] = (int) $payload['sort_order'];
        }
        if (array_key_exists('active', $payload)) {
            $data['active'] = ! empty($payload['active']) ? 1 : 0;
        }
        foreach (['buy_plan_intro_fa', 'buy_plan_intro_en', 'buy_category_intro_fa', 'buy_category_intro_en'] as $col) {
            if (array_key_exists($col, $payload) && Schema::hasColumn('svp_plan_categories', $col)) {
                $data[$col] = (string) $payload[$col];
            }
        }

        if ($id > 0) {
            $existing = DB::table('svp_plan_categories')->where('id', $id)->first();
            if (! $existing) {
                return svp_err('not_found', ['code' => 'not_found']);
            }
            if ($resellerActor > 0 && ! $this->planResellerRules->resellerMayUsePanelCatalog(
                $resellerActor,
                max(1, (int) ($existing->panel_id ?? 1))
            )) {
                return svp_err('forbidden', ['code' => 'panel_not_allowed']);
            }
            // WP update: do not rewrite slug/panel_id.
            if ($data !== []) {
                DB::table('svp_plan_categories')->where('id', $id)->update($data);
            }

            return svp_ok(['id' => $id, 'code' => 'updated']);
        }

        $slug = strtolower(substr(preg_replace('/[^a-z0-9_]/', '', (string) ($payload['slug'] ?? '')) ?: '', 0, 32));
        $label = (string) ($payload['label'] ?? '');
        if ($slug === '' || $label === '') {
            return svp_err('invalid', ['code' => 'invalid']);
        }
        if (DB::table('svp_plan_categories')->where('panel_id', $panelId)->where('slug', $slug)->exists()) {
            return svp_err('dup', ['code' => 'dup']);
        }

        $insert = array_merge([
            'panel_id' => $panelId,
            'slug' => $slug,
            'label' => $label,
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
            'active' => array_key_exists('active', $payload) ? (! empty($payload['active']) ? 1 : 0) : 1,
            'created_at' => now(),
        ], $data);
        $newId = DB::table('svp_plan_categories')->insertGetId($insert);

        return svp_ok(['id' => $newId, 'code' => 'added']);
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
        if ($id < 1) {
            return svp_err('invalid');
        }
        $rec = DB::table('svp_receipts')->where('id', $id)->first();
        if (! $rec) {
            return svp_err('not_found');
        }
        $tx = Schema::hasTable('svp_transactions')
            ? DB::table('svp_transactions')->where('id', (int) ($rec->transaction_id ?? 0))->first()
            : null;

        $amountAdj = null;
        if (array_key_exists('amount', $payload)) {
            $newAmount = (float) str_replace(',', '.', (string) $payload['amount']);
            if ($tx) {
                $amountAdj = $this->adjustReceiptAmount($rec, $tx, $newAmount);
                if (empty($amountAdj['ok'])) {
                    return svp_err((string) ($amountAdj['message'] ?? 'bad_amount'), $amountAdj);
                }
                $rec = DB::table('svp_receipts')->where('id', $id)->first() ?: $rec;
                $tx = DB::table('svp_transactions')->where('id', (int) ($rec->transaction_id ?? 0))->first() ?: $tx;
            } else {
                if ($newAmount < 0) {
                    return svp_err('bad_amount');
                }
                DB::table('svp_receipts')->where('id', $id)->update(['amount' => round($newAmount, 2)]);
                $amountAdj = ['ok' => true, 'message' => 'amount_updated'];
            }
        }

        $noteCols = array_values(array_filter(
            ['note', 'admin_note'],
            fn ($c) => Schema::hasColumn('svp_receipts', $c)
        ));
        $noteData = [];
        foreach ($noteCols as $col) {
            if (array_key_exists($col, $payload)) {
                $noteData[$col] = $payload[$col];
            } elseif ($col === 'note' && array_key_exists('admin_note', $payload) && ! Schema::hasColumn('svp_receipts', 'admin_note')) {
                $noteData['note'] = $payload['admin_note'];
            }
        }
        if ($noteData !== []) {
            DB::table('svp_receipts')->where('id', $id)->update($noteData);
        }

        $newStatus = strtolower(trim((string) ($payload['status'] ?? $payload['receipt_new_status'] ?? '')));
        if ($newStatus !== '') {
            if (! $tx && in_array($newStatus, ['approved', 'rejected'], true)) {
                // Status transitions that fulfill/reject still need a linked transaction (WP no_tx).
                return svp_err('no_tx');
            }
            $label = $actor?->username ?? ($actor?->email ?? 'admin');
            $reason = (string) ($payload['reject_reason'] ?? '');

            return $this->adminSetReceiptStatus($id, $newStatus, $label, $reason);
        }

        if (is_array($amountAdj)) {
            return svp_ok(array_merge(['receipt_id' => $id], $amountAdj));
        }

        return svp_ok(['receipt_id' => $id]);
    }

    /**
     * @param  object  $rec
     * @param  object  $tx
     * @return array{ok:bool, message?:string, warnings?:list<string>}
     */
    protected function adjustReceiptAmount(object $rec, object $tx, float $newAmount): array
    {
        $old = round((float) ($rec->amount ?? 0), 2);
        $new = round($newAmount, 2);
        if ($new < 0) {
            return ['ok' => false, 'message' => 'bad_amount'];
        }
        if (abs($new - $old) < 0.009) {
            return ['ok' => true, 'message' => 'amount_unchanged'];
        }

        $warnings = [];
        $meta = json_decode((string) ($tx->meta_json ?? ''), true);
        if (is_array($meta) && ! empty($meta['referral_commission_paid'])) {
            $warnings[] = 'commission_may_need_manual_review';
        }

        $status = (string) ($rec->status ?? '');
        $type = (string) ($tx->type ?? '');
        if ($status === 'approved' && in_array($type, ['topup', 'wallet_topup'], true)) {
            $delta = $new - $old;
            $uid = (int) ($rec->user_id ?? 0);
            if ($uid > 0 && Schema::hasTable('svp_users') && Schema::hasColumn('svp_users', 'balance')) {
                DB::table('svp_users')->where('id', $uid)->update([
                    'balance' => DB::raw('balance + '.((float) $delta)),
                ]);
            }
        }

        DB::table('svp_receipts')->where('id', (int) $rec->id)->update(['amount' => $new]);
        DB::table('svp_transactions')->where('id', (int) $tx->id)->update(['amount' => $new]);

        $msg = ($status === 'approved' && in_array($type, ['topup', 'wallet_topup'], true))
            ? 'topup_delta_applied'
            : 'amount_updated';
        $out = ['ok' => true, 'message' => $msg];
        if ($warnings !== []) {
            $out['warnings'] = $warnings;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    protected function adminSetReceiptStatus(int $receiptId, string $newStatus, string $adminLabel, string $rejectReason = ''): array
    {
        $rec = DB::table('svp_receipts')->where('id', $receiptId)->first();
        if (! $rec) {
            return svp_err('not_found');
        }
        $old = (string) ($rec->status ?? '');
        $new = strtolower(trim($newStatus));

        if (in_array($old, ['pending', 'processing'], true) && $new === 'approved') {
            if ($old === 'processing') {
                DB::table('svp_receipts')->where('id', $receiptId)->update(['status' => 'pending']);
            }

            return $this->receiptProcessor->approve($receiptId, $adminLabel);
        }
        if (in_array($old, ['pending', 'processing'], true) && $new === 'rejected') {
            return $this->receiptProcessor->reject($receiptId, $adminLabel, $rejectReason);
        }
        if ($old === 'processing' && $new === 'pending') {
            DB::table('svp_receipts')->where('id', $receiptId)->update(['status' => 'pending']);

            return svp_ok(['receipt_id' => $receiptId, 'status' => 'pending']);
        }
        if ($old === 'rejected' && in_array($new, ['approved', 'pending'], true)) {
            DB::table('svp_receipts')->where('id', $receiptId)->update([
                'status' => $new === 'approved' ? 'pending' : 'pending',
                'reject_reason' => null,
                'decided_at' => null,
                'decided_by' => null,
            ]);
            if ($new === 'approved') {
                return $this->receiptProcessor->approve($receiptId, $adminLabel);
            }

            return svp_ok(['receipt_id' => $receiptId, 'status' => 'pending']);
        }

        return svp_err('invalid_status', ['old' => $old, 'new' => $new]);
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
        $serviceId = (int) ($payload['service_id'] ?? 0);
        if ($serviceId < 1) {
            return svp_err('invalid', ['message' => 'invalid_service']);
        }
        $svc = SvpService::query()->find($serviceId);
        if (! $svc || $svc->deleted_at) {
            return svp_err('not_found');
        }

        $email = (string) ($svc->email ?? '');
        $panelId = max(1, (int) ($svc->panel_id ?? 1));
        $inboundId = (int) ($svc->inbound_id ?? 0);
        $isL2tp = strtolower((string) ($svc->service_type ?? '')) === 'l2tp';

        $panelAbsent = false;
        if (! $isL2tp) {
            try {
                $panelRow = $this->panelFactory->loadPanelRow($panelId) ?? [];
                $result = $this->panelFactory
                    ->forPanelId($panelId, $panelRow)
                    ->deleteClient($panelRow, $serviceId);
                if (empty($result['ok'])) {
                    return svp_err('panel_delete_failed', [
                        'message' => 'panel_delete_failed',
                        'reason' => (string) ($result['message'] ?? $result['reason'] ?? 'failed'),
                    ]);
                }
                $panelAbsent = ! empty($result['panel_absent']);
            } catch (\Throwable $e) {
                return svp_err('panel_delete_failed', [
                    'message' => 'panel_delete_failed',
                    'reason' => $e->getMessage(),
                ]);
            }

            $this->invalidatePanelClientCacheRow($panelId, $inboundId, $email);
        }

        SvpService::query()->where('id', $serviceId)->update(['deleted_at' => now()]);

        $out = ['service_id' => $serviceId, 'message' => 'deleted'];
        if ($panelAbsent) {
            $out['panel_absent'] = true;
        }

        return svp_ok($out);
    }

    protected function invalidatePanelClientCacheRow(int $panelId, int $inboundId, string $email): void
    {
        $email = trim($email);
        if ($inboundId < 1 || $email === '' || ! Schema::hasTable('svp_panel_inbound_clients')) {
            return;
        }
        DB::table('svp_panel_inbound_clients')
            ->where('panel_id', max(1, $panelId))
            ->where('inbound_id', $inboundId)
            ->where('email', $email)
            ->delete();
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
