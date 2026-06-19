<?php

namespace App\Services\Commerce;

use App\Models\SvpPlan;
use App\Models\SvpService;
use App\Modules\XuiPanel\Services\XuiClient;
use Illuminate\Support\Facades\DB;

class ServiceProvisionService
{
    public function __construct(
        protected XuiClient $xui,
        protected CheckoutPriceRenewService $renewPricing,
    ) {}

    /** @return array<string, mixed> */
    public function findService(int $serviceId): ?array
    {
        $svc = SvpService::query()->find($serviceId);

        return $svc ? $svc->toArray() : null;
    }

    /** @return array<string, mixed> */
    public function renew(int $serviceId, string $mode = 'free'): array
    {
        $svc = SvpService::query()->find($serviceId);
        if (! $svc) {
            return svp_err('not_found');
        }

        if ($mode === 'invoice') {
            $plan = $svc->plan_id ? SvpPlan::query()->find((int) $svc->plan_id) : null;
            $amount = $this->renewPricing->checkoutPriceRenew($svc, $plan);
            if ($amount <= 0) {
                return $this->renew($serviceId, 'free');
            }
            $txId = (int) DB::table('svp_transactions')->insertGetId([
                'user_id' => $svc->user_id,
                'service_id' => $serviceId,
                'amount' => $amount,
                'type' => 'service_renew',
                'status' => 'pending',
                'meta_json' => json_encode(['service_id' => $serviceId, 'base_amount' => $amount]),
                'created_at' => now(),
            ]);

            return svp_ok(['transaction_id' => $txId, 'amount' => $amount]);
        }

        $days = 30;
        if ($svc->plan_id) {
            $plan = SvpPlan::query()->find($svc->plan_id);
            if ($plan && (int) ($plan->duration_days ?? 0) > 0) {
                $days = (int) $plan->duration_days;
            }
        }

        $base = $svc->expires_at && $svc->expires_at->isFuture() ? $svc->expires_at : now();
        $svc->expires_at = $base->copy()->addDays($days);
        $svc->save();
        $this->syncPanel($svc);

        return svp_ok(['transaction_id' => 0]);
    }

    /** @return array<string, mixed> */
    public function addVolume(int $serviceId, int $gb, string $mode = 'free'): array
    {
        if ($gb < 1) {
            return svp_err('invalid');
        }
        $svc = SvpService::query()->find($serviceId);
        if (! $svc) {
            return svp_err('not_found');
        }

        if ($mode === 'invoice') {
            $plan = $svc->plan_id ? SvpPlan::query()->find((int) $svc->plan_id) : null;
            $amount = $plan
                ? $this->renewPricing->checkoutPriceAddVolume($svc, $plan, $gb)
                : 0;
            if ($amount <= 0) {
                return $this->addVolume($serviceId, $gb, 'free');
            }
            $txId = (int) DB::table('svp_transactions')->insertGetId([
                'user_id' => $svc->user_id,
                'service_id' => $serviceId,
                'amount' => $amount,
                'type' => 'service_add_volume',
                'status' => 'pending',
                'meta_json' => json_encode(['service_id' => $serviceId, 'gb' => $gb, 'base_amount' => $amount]),
                'created_at' => now(),
            ]);

            return svp_ok(['transaction_id' => $txId, 'amount' => $amount]);
        }

        $bytes = $gb * 1024 * 1024 * 1024;
        $svc->total_traffic = (int) $svc->total_traffic + $bytes;
        $svc->save();
        $this->syncPanel($svc);

        return svp_ok(['transaction_id' => 0]);
    }

    /** @return array<string, mixed> */
    public function addSlots(int $serviceId, int $slots, string $mode = 'free'): array
    {
        if ($slots < 1) {
            return svp_err('invalid');
        }
        $svc = SvpService::query()->find($serviceId);
        if (! $svc) {
            return svp_err('not_found');
        }

        if ($mode === 'invoice') {
            $plan = $svc->plan_id ? SvpPlan::query()->find((int) $svc->plan_id) : null;
            $amount = $plan
                ? $this->renewPricing->checkoutPriceAddSlots($plan, $slots)
                : 0;
            if ($amount <= 0) {
                return $this->addSlots($serviceId, $slots, 'free');
            }
            $txId = (int) DB::table('svp_transactions')->insertGetId([
                'user_id' => $svc->user_id,
                'service_id' => $serviceId,
                'amount' => $amount,
                'type' => 'service_add_slots',
                'status' => 'pending',
                'meta_json' => json_encode(['service_id' => $serviceId, 'slots' => $slots, 'base_amount' => $amount]),
                'created_at' => now(),
            ]);

            return svp_ok(['transaction_id' => $txId, 'amount' => $amount]);
        }

        $svc->client_slots = max(1, (int) ($svc->client_slots ?? 1)) + $slots;
        $svc->save();
        $this->syncPanel($svc);

        return svp_ok(['transaction_id' => 0]);
    }

    /** @return array<string, mixed> */
    public function reduceSlots(int $serviceId, int $slots): array
    {
        if ($slots < 1) {
            return svp_err('invalid');
        }
        $svc = SvpService::query()->find($serviceId);
        if (! $svc) {
            return svp_err('not_found');
        }

        $svc->client_slots = max(1, (int) ($svc->client_slots ?? 1) - $slots);
        $svc->save();
        $this->syncPanel($svc);

        return svp_ok(['service_id' => $serviceId]);
    }

    /** @return array<string, mixed> */
    public function reduceVolume(int $serviceId, int $gb): array
    {
        if ($gb < 1) {
            return svp_err('invalid');
        }
        $svc = SvpService::query()->find($serviceId);
        if (! $svc) {
            return svp_err('not_found');
        }

        $bytes = $gb * 1024 * 1024 * 1024;
        $svc->total_traffic = max(0, (int) $svc->total_traffic - $bytes);
        $svc->save();
        $this->syncPanel($svc);

        return svp_ok();
    }

    /** @return array<string, mixed> */
    public function addDays(int $serviceId, int $days): array
    {
        if ($days < 1) {
            return svp_err('invalid');
        }
        $svc = SvpService::query()->find($serviceId);
        if (! $svc) {
            return svp_err('not_found');
        }

        $base = $svc->expires_at && $svc->expires_at->isFuture() ? $svc->expires_at : now();
        $svc->expires_at = $base->copy()->addDays($days);
        $svc->save();
        $this->syncPanel($svc);

        return svp_ok();
    }

    /** @return array<string, mixed> */
    public function reduceDays(int $serviceId, int $days): array
    {
        if ($days < 1) {
            return svp_err('invalid');
        }
        $svc = SvpService::query()->find($serviceId);
        if (! $svc) {
            return svp_err('not_found');
        }

        $base = $svc->expires_at ?? now();
        $svc->expires_at = $base->copy()->subDays($days);
        $svc->save();
        $this->syncPanel($svc);

        return svp_ok();
    }

    /** @return array<string, mixed> */
    public function toggleEnable(int $serviceId, bool $enabled): array
    {
        $svc = SvpService::query()->find($serviceId);
        if (! $svc) {
            return svp_err('not_found');
        }

        DB::table('svp_services')->where('id', $serviceId)->update([
            'client_enabled' => $enabled ? 1 : 0,
        ]);
        $this->syncPanel($svc);

        return svp_ok();
    }

    protected function syncPanel(SvpService $svc): void
    {
        $panel = DB::table('svp_panels')->where('id', (int) ($svc->panel_id ?? 0))->first();
        if ($panel) {
            $this->xui->syncService((array) $panel, (int) $svc->id);
        }
    }
}
