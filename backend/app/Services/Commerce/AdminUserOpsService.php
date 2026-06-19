<?php

namespace App\Services\Commerce;

use App\Models\SvpPlan;
use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use Illuminate\Support\Facades\DB;

class AdminUserOpsService
{
    public function __construct(
        protected ServiceProvisioner $provisioner,
        protected BotRuntime $botRuntime,
    ) {}

    /**
     * @return array{ok: bool, reason?: string, service_id?: int, transaction_id?: int}
     */
    public function adminCreateService(
        int $targetUserId,
        int $planId,
        ?int $volumeGb,
        string $mode,
        int $resellerActorId = 0,
    ): array {
        $mode = strtolower(trim($mode));
        if (! in_array($mode, ['free', 'wallet', 'invoice'], true)) {
            return ['ok' => false, 'reason' => 'bad_mode'];
        }

        $user = SvpUser::query()->find($targetUserId);
        $plan = SvpPlan::query()->find($planId);
        if (! $user || (string) $user->status !== 'approved') {
            return ['ok' => false, 'reason' => 'bad_user'];
        }
        if (! $plan || ! $plan->active) {
            return ['ok' => false, 'reason' => 'bad_plan'];
        }
        if ($this->isPerGb($plan)) {
            if ($volumeGb === null || $volumeGb < 1) {
                return ['ok' => false, 'reason' => 'volume_out_of_range'];
            }
        }

        $price = $this->priceNewService($plan, $volumeGb);

        if ($mode === 'free') {
            if ($resellerActorId > 0) {
                return ['ok' => false, 'reason' => 'forbidden_free_reseller'];
            }

            return $this->provisionFree($user, $plan, $planId, $volumeGb);
        }

        if ($price <= 0) {
            if ($resellerActorId > 0) {
                return ['ok' => false, 'reason' => 'forbidden_free_reseller'];
            }

            return $this->adminCreateService($targetUserId, $planId, $volumeGb, 'free', 0);
        }

        if ($mode === 'wallet') {
            return $this->provisionWallet($user, $plan, $planId, $volumeGb, $price, $resellerActorId);
        }

        return $this->provisionInvoice($user, $planId, $volumeGb, $price, $resellerActorId);
    }

    /** @return array{ok: bool, reason?: string, service_id?: int} */
    protected function provisionFree(SvpUser $user, SvpPlan $plan, int $planId, ?int $volumeGb): array
    {
        $det = $this->provisioner->createFromPlan((int) $user->id, $planId, $volumeGb);
        if (empty($det['ok'])) {
            return ['ok' => false, 'reason' => (string) ($det['reason'] ?? 'provision_failed')];
        }
        $sid = (int) ($det['service_id'] ?? 0);
        DB::table('svp_transactions')->insert([
            'user_id' => $user->id,
            'service_id' => $sid,
            'amount' => 0,
            'type' => 'purchase',
            'status' => 'approved',
            'meta_json' => json_encode(['plan_id' => $planId, 'volume_gb' => $volumeGb, 'admin_gift' => true]),
            'created_at' => now(),
        ]);

        return ['ok' => true, 'service_id' => $sid];
    }

    /** @return array{ok: bool, reason?: string, service_id?: int} */
    protected function provisionWallet(
        SvpUser $user,
        SvpPlan $plan,
        int $planId,
        ?int $volumeGb,
        float $price,
        int $resellerActorId,
    ): array {
        return DB::transaction(function () use ($user, $plan, $planId, $volumeGb, $price, $resellerActorId) {
            $billingUserId = $resellerActorId > 0 ? $resellerActorId : (int) $user->id;
            $billingUser = SvpUser::query()->lockForUpdate()->find($billingUserId);
            if (! $billingUser || (float) $billingUser->balance < $price) {
                return ['ok' => false, 'reason' => 'insufficient_balance'];
            }
            $billingUser->balance = bcsub((string) $billingUser->balance, (string) $price, 2);
            $billingUser->save();

            $det = $this->provisioner->createFromPlan((int) $user->id, $planId, $volumeGb);
            if (empty($det['ok'])) {
                $billingUser->balance = bcadd((string) $billingUser->balance, (string) $price, 2);
                $billingUser->save();

                return ['ok' => false, 'reason' => (string) ($det['reason'] ?? 'provision_failed')];
            }

            $sid = (int) ($det['service_id'] ?? 0);
            $meta = ['plan_id' => $planId, 'volume_gb' => $volumeGb, 'admin_wallet' => true];
            if ($resellerActorId > 0) {
                $meta['billing_reseller_svp_id'] = $resellerActorId;
            }
            DB::table('svp_transactions')->insert([
                'user_id' => $user->id,
                'service_id' => $sid,
                'amount' => $price,
                'type' => 'purchase',
                'status' => 'approved',
                'meta_json' => json_encode($meta),
                'created_at' => now(),
            ]);

            return ['ok' => true, 'service_id' => $sid];
        });
    }

    /** @return array{ok: bool, reason?: string, transaction_id?: int} */
    protected function provisionInvoice(SvpUser $user, int $planId, ?int $volumeGb, float $price, int $resellerActorId): array
    {
        $meta = [
            'plan_id' => $planId,
            'volume_gb' => $volumeGb,
            'admin_invoice' => true,
        ];
        if ($resellerActorId > 0) {
            $meta['invoice_card_owner_scope_svp_id'] = $resellerActorId;
            $meta['billing_reseller_svp_id'] = $resellerActorId;
        }

        $tid = DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'service_id' => null,
            'amount' => round($price, 2),
            'type' => 'purchase',
            'status' => 'pending',
            'meta_json' => json_encode($meta),
            'created_at' => now(),
        ]);

        if ($tid < 1) {
            return ['ok' => false, 'reason' => 'checkout_failed'];
        }

        $sent = false;
        $text = '🧾 فاکتور سفارش (ادمین) — '.number_format($price).' تومان';
        if ((int) ($user->tg_user_id ?? 0) > 0) {
            $this->botRuntime->sendMessage(new BotContext('telegram'), (int) $user->tg_user_id, $text);
            $sent = true;
        }
        if ((int) ($user->bale_user_id ?? 0) > 0) {
            $this->botRuntime->sendMessage(new BotContext('bale'), (int) $user->bale_user_id, $text);
            $sent = true;
        }
        if (! $sent) {
            DB::table('svp_transactions')->where('id', $tid)->update(['status' => 'cancelled']);

            return ['ok' => false, 'reason' => 'checkout_failed'];
        }

        return ['ok' => true, 'transaction_id' => (int) $tid];
    }

    protected function isPerGb(SvpPlan $plan): bool
    {
        return (string) ($plan->pricing_type ?? '') === 'per_gb';
    }

    public function priceNewService(SvpPlan $plan, ?int $volumeGb): float
    {
        if ($this->isPerGb($plan)) {
            return round((float) ($plan->price_per_gb ?? 0) * max(1, (int) $volumeGb), 2);
        }

        return round((float) ($plan->price ?? 0), 2);
    }
}
