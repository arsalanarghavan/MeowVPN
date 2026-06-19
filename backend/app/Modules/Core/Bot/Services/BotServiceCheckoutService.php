<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpPlan;
use App\Models\SvpService;
use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Services\Commerce\CheckoutPriceRenewService;
use App\Services\Commerce\ServiceProvisionService;
use Illuminate\Support\Facades\DB;

class BotServiceCheckoutService
{
    public function __construct(
        protected CheckoutPriceRenewService $renewPricing,
        protected ServiceProvisionService $provision,
    ) {}

    /** @return array<string, mixed> */
    public function beginRenew(SvpUser $user, BotContext $ctx, int $serviceId): array
    {
        $svc = SvpService::query()->find($serviceId);
        if (! $svc || (int) $svc->user_id !== (int) $user->id) {
            return ['ok' => false, 'message' => 'not_found'];
        }

        $amount = $this->calculateRenewAmount($svc);

        return $this->finalizeOrCreateTx($user, $ctx, 'service_renew', $serviceId, $amount, [
            'service_id' => $serviceId,
        ], fn () => $this->provision->renew($serviceId, 'free'));
    }

    /** @return array<string, mixed> */
    public function beginAddVolume(SvpUser $user, BotContext $ctx, int $serviceId, int $gb): array
    {
        $svc = SvpService::query()->find($serviceId);
        if (! $svc || (int) $svc->user_id !== (int) $user->id) {
            return ['ok' => false, 'message' => 'not_found'];
        }
        if ($gb < 1) {
            return ['ok' => false, 'message' => 'invalid'];
        }

        $amount = $this->calculateAddVolumeAmount($svc, $gb);

        return $this->finalizeOrCreateTx($user, $ctx, 'service_add_volume', $serviceId, $amount, [
            'service_id' => $serviceId,
            'gb' => $gb,
        ], fn () => $this->provision->addVolume($serviceId, $gb, 'free'));
    }

    /** @return array<string, mixed> */
    public function beginAddSlots(SvpUser $user, BotContext $ctx, int $serviceId, int $slots): array
    {
        $svc = SvpService::query()->find($serviceId);
        if (! $svc || (int) $svc->user_id !== (int) $user->id) {
            return ['ok' => false, 'message' => 'not_found'];
        }
        if ($slots < 1) {
            return ['ok' => false, 'message' => 'invalid'];
        }

        $amount = $this->calculateAddSlotsAmount($svc, $slots);

        return $this->finalizeOrCreateTx($user, $ctx, 'service_add_slots', $serviceId, $amount, [
            'service_id' => $serviceId,
            'slots' => $slots,
        ], fn () => $this->provision->addSlots($serviceId, $slots, 'free'));
    }

    public function calculateRenewAmount(SvpService $svc): float
    {
        $plan = $svc->plan_id ? SvpPlan::query()->find((int) $svc->plan_id) : null;

        return $this->renewPricing->checkoutPriceRenew($svc, $plan);
    }

    public function calculateAddVolumeAmount(SvpService $svc, int $gb): float
    {
        $plan = $svc->plan_id ? SvpPlan::query()->find((int) $svc->plan_id) : null;

        return $plan ? $this->renewPricing->checkoutPriceAddVolume($svc, $plan, $gb) : 0.0;
    }

    public function calculateAddSlotsAmount(SvpService $svc, int $slots): float
    {
        $plan = $svc->plan_id ? SvpPlan::query()->find((int) $svc->plan_id) : null;

        return $plan ? $this->renewPricing->checkoutPriceAddSlots($plan, $slots) : 0.0;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  callable(): array<string, mixed>  $freeApply
     * @return array<string, mixed>
     */
    protected function finalizeOrCreateTx(
        SvpUser $user,
        BotContext $ctx,
        string $type,
        int $serviceId,
        float $amount,
        array $meta,
        callable $freeApply,
    ): array {
        if ($amount <= 0) {
            $result = $freeApply();
            if (empty($result['ok'])) {
                return $result;
            }

            return ['ok' => true, 'free' => true, 'service_id' => $serviceId];
        }

        $meta['service_id'] = $serviceId;
        $meta['platform'] = $ctx->platform;
        $meta['base_amount'] = $amount;

        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'service_id' => $serviceId,
            'amount' => $amount,
            'type' => $type,
            'status' => 'pending',
            'meta_json' => json_encode($meta),
            'created_at' => now(),
        ]);

        return ['ok' => true, 'transaction_id' => $txId, 'amount' => $amount, 'service_id' => $serviceId];
    }
}
