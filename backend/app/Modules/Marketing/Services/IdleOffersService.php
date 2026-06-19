<?php

namespace App\Modules\Marketing\Services;

use App\Models\SvpUser;
use App\Modules\Core\Services\UserBotNotifyService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IdleOffersService
{
    public function __construct(
        protected SettingsStore $settings,
        protected UserBotNotifyService $notify,
    ) {}

    public function run(): void
    {
        if (! $this->settings->get('enabled', true)) {
            return;
        }
        if (svp_modules()->isEnabled('marketing')) {
            return;
        }
        if (! $this->settings->get('notify_idle_enabled', false) || ! Schema::hasTable('svp_users')) {
            return;
        }

        $afterDays = max(7, (int) $this->settings->get('notify_idle_after_days', 45));
        $coolDays = max(7, (int) $this->settings->get('notify_idle_cooldown_days', 90));
        $cutoff = now()->subDays($afterDays)->timestamp;

        $users = DB::table('svp_users')->where('status', 'approved')->orderBy('id')->limit(80)->get();
        foreach ($users as $row) {
            $uid = (int) $row->id;
            if (Cache::has('svp_idle_ping_u'.$uid)) {
                continue;
            }
            $last = $this->lastApprovedPurchaseAt($uid);
            if ($last < 1 || $last > $cutoff) {
                continue;
            }
            $user = SvpUser::query()->find($uid);
            if (! $user) {
                continue;
            }
            $this->notify->sendToUser(
                $user,
                "👋 مدتی است خرید یا تمدیدی از حسابت ثبت نشده.\nاگر هنوز به VPN نیاز داری، از منوی ربات سرویس‌ها را ببین."
            );
            Cache::put('svp_idle_ping_u'.$uid, 1, now()->addDays($coolDays));
        }
    }

    protected function lastApprovedPurchaseAt(int $userId): int
    {
        if (! Schema::hasTable('svp_transactions')) {
            return 0;
        }
        $ts = DB::table('svp_transactions')
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->max('created_at');

        return $ts ? strtotime((string) $ts.' UTC') : 0;
    }
}
