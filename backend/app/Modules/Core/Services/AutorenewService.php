<?php

namespace App\Modules\Core\Services;

use App\Models\SvpPlan;
use App\Models\SvpUser;
use App\Services\Commerce\CheckoutPriceRenewService;
use App\Services\Commerce\ServiceProvisionService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AutorenewService
{
    private const LOCK_KEY = 'svp_cron_autorenew_lock';

    public function __construct(
        protected SettingsStore $settings,
        protected ServiceProvisionService $provision,
        protected UserBotNotifyService $notify,
        protected CheckoutPriceRenewService $checkoutPrice,
    ) {}

    public function run(): void
    {
        if (! $this->settings->get('enabled', true) || ! Schema::hasTable('svp_services')) {
            return;
        }
        if (Cache::has(self::LOCK_KEY)) {
            return;
        }
        Cache::put(self::LOCK_KEY, 1, now()->addMinutes(55));
        try {
            DB::table('svp_services')
                ->whereNull('deleted_at')
                ->where('autorenew', 1)
                ->whereNotNull('expires_at')
                ->orderBy('id')
                ->chunkById(100, function ($services) {
                    foreach ($services as $svc) {
                        $this->renewOne($svc);
                    }
                });
        } finally {
            Cache::forget(self::LOCK_KEY);
        }
    }

    protected function renewOne(object $svc): void
    {
        if ((string) ($svc->service_type ?? 'xray') === 'l2tp') {
            return;
        }
        $exp = strtotime((string) $svc->expires_at.' UTC');
        if ($exp === false) {
            return;
        }
        $window = max(3600, (int) $this->settings->get('autorenew_window_seconds', 86400));
        if (($exp - time()) > $window) {
            return;
        }
        $user = SvpUser::query()->find((int) $svc->user_id);
        if (! $user || (string) $user->status !== 'approved') {
            return;
        }
        $price = $this->renewPrice($svc);
        if ($price > 0 && (float) $user->balance < $price) {
            $this->notify->sendToUser($user, '❌ تمدید خودکار سرویس «'.(string) ($svc->remark ?: $svc->email).'» ناموفق: موجودی کافی نیست.');

            return;
        }
        if ($price > 0) {
            $deducted = DB::transaction(function () use ($user, $price) {
                $locked = SvpUser::query()->lockForUpdate()->find($user->id);
                if (! $locked || (float) $locked->balance < $price) {
                    return false;
                }
                $locked->balance = bcsub((string) $locked->balance, (string) $price, 2);
                $locked->save();

                return true;
            });
            if (! $deducted) {
                $this->notify->sendToUser($user, '❌ تمدید خودکار سرویس «'.(string) ($svc->remark ?: $svc->email).'» ناموفق: موجودی کافی نیست.');

                return;
            }
        }
        $res = $this->provision->renew((int) $svc->id, 'free');
        if (empty($res['ok'])) {
            if ($price > 0) {
                SvpUser::query()->where('id', $user->id)->increment('balance', $price);
            }
            $this->notify->sendToUser($user, '❌ تمدید خودکار سرویس «'.(string) ($svc->remark ?: $svc->email).'» ناموفق شد.');
        }
    }

    protected function renewPrice(object $svc): float
    {
        if ((int) ($svc->plan_id ?? 0) < 1) {
            return 0.0;
        }
        $plan = SvpPlan::query()->find((int) $svc->plan_id);
        if (! $plan) {
            return 0.0;
        }

        return $this->checkoutPrice->checkoutPriceRenew($svc, $plan);
    }
}
