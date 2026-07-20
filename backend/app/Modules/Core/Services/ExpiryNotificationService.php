<?php

namespace App\Modules\Core\Services;

use App\Models\SvpUser;
use App\Modules\L2tp\Services\L2tpProvisionerService;
use App\Modules\XuiPanel\Services\PurgeExpiredService;
use App\Services\NotificationDedupService;
use App\Services\ServiceAlertsHelper;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExpiryNotificationService
{
    /** @var array<int, int> */
    protected array $cleanedL2tpExpired = [];

    public function __construct(
        protected SettingsStore $settings,
        protected NotifySettings $notify,
        protected UserBotNotifyService $notifyUser,
        protected L2tpProvisionerService $l2tp,
        protected ServiceAlertsHelper $alerts,
        protected PurgeExpiredService $purge,
        protected NotificationDedupService $dedup,
    ) {}

    public function run(): void
    {
        if (! $this->settings->get('enabled', true) || ! Schema::hasTable('svp_services')) {
            return;
        }

        DB::table('svp_services')->whereNull('deleted_at')->orderBy('id')->chunkById(200, function ($services) {
            foreach ($services as $svc) {
                $this->processService($svc);
            }
        });
    }

    protected function processService(object $svc): void
    {
        if (! $this->alertsEnabled($svc)) {
            return;
        }

        $user = SvpUser::query()->find((int) $svc->user_id);
        if (! $user || (string) $user->status !== 'approved') {
            return;
        }

        $isL2tp = L2tpProvisionerService::isL2tp($svc);
        if ($isL2tp) {
            $this->l2tp->refreshUsage($svc);
            $svc = DB::table('svp_services')->where('id', (int) $svc->id)->first() ?? $svc;
            if (! empty($svc->expires_at)) {
                $exp = strtotime((string) $svc->expires_at.' UTC');
                $sid = (int) $svc->id;
                if ($exp !== false && $exp < time() && empty($this->cleanedL2tpExpired[$sid])) {
                    $this->l2tp->deleteExpiredUser($svc);
                    $this->cleanedL2tpExpired[$sid] = 1;
                }
            }
        }

        $this->processVolumeAlert($svc, $user);
        if (! $isL2tp) {
            $this->maybeFillIpFromLog($svc);
            $this->maybeIpFillAlert($svc, $user);
            $this->maybeTrafficStaleAlert($svc, $user);
        }
        $this->processExpiryAlerts($svc, $user);
    }

    protected function processVolumeAlert(object $svc, SvpUser $user): void
    {
        if (! $this->notify->volumeOn() || ! $this->alerts->volumeEnabled($svc)) {
            return;
        }
        $total = (int) ($svc->total_traffic ?? 0);
        $used = (int) ($svc->used_traffic ?? 0);
        $pctTh = max(1, $this->alerts->effectiveLowTrafficPct($svc));
        if ($total > 0) {
            $remainingPct = (int) floor((($total - $used) * 100) / $total);
            if ($remainingPct <= $pctTh && $remainingPct >= 0) {
                $key = 'svc'.(int) $svc->id.':low:'.$pctTh;
                if ($this->claimBucket($key)) {
                    $this->notifyUser->sendToUser(
                        $user,
                        '⚠️ حجم سرویس «'.(string) ($svc->remark ?: $svc->email).'» به '.$remainingPct.'٪ رسید.'
                    );
                }
            }
        }
    }

    protected function processExpiryAlerts(object $svc, SvpUser $user): void
    {
        if (! $this->notify->expiryOn() || ! $this->alerts->expiryAlertEnabled($svc)) {
            return;
        }
        if (empty($svc->expires_at)) {
            return;
        }
        $exp = strtotime((string) $svc->expires_at.' UTC');
        if ($exp === false) {
            return;
        }
        $days = (int) floor(($exp - time()) / 86400);
        $purgeCovers = $days < 0 && $this->coversExpiredByPurge($svc);
        $warnDays = $this->alerts->effectiveExpiryDays($svc);
        $label = (string) ($svc->remark ?: $svc->email);

        if ($days >= 0 && in_array($days, $warnDays, true)) {
            $this->sendExpiryWarn($svc, $user, $days, $label);
        }
        if ($days < 0 && ! $purgeCovers && in_array($days, $warnDays, true)) {
            $this->sendExpiryWarn($svc, $user, $days, $label);
        }
        if ($days < 0 && ! $purgeCovers && $this->notify->afterExpireOn()) {
            $bucketKey = 'svc'.(int) $svc->id.':expired:'.gmdate('Y-m-d', $exp);
            if ($this->claimBucket($bucketKey)) {
                $this->notifyUser->sendToUser(
                    $user,
                    '⛔ سرویس «'.$label.'» منقضی شده است. برای تمدید از ربات اقدام کنید.'
                );
            }
        }
    }

    protected function sendExpiryWarn(object $svc, SvpUser $user, int $days, string $label): void
    {
        $key = 'svc'.(int) $svc->id.':expd:'.$days;
        if (! $this->claimBucket($key)) {
            return;
        }
        $msg = $days === 0
            ? '⏳ سرویس «'.$label.'» امروز منقضی می‌شود.'
            : ($days > 0
                ? '⏳ سرویس «'.$label.'» تا '.$days.' روز دیگر منقضی می‌شود.'
                : '⏳ سرویس «'.$label.'» '.$days.' روز است که منقضی شده است.');
        $this->notifyUser->sendToUser($user, $msg);
    }

    protected function maybeIpFillAlert(object $svc, SvpUser $user): void
    {
        if (! $this->notify->usersOn() || ! $this->alerts->usersAlertEnabled($svc)) {
            return;
        }
        $lim = $this->alerts->clientLimitIp($svc);
        if ($lim < 1) {
            return;
        }
        $nIp = $this->alerts->clientIpCount($svc);
        $ipTh = $this->alerts->effectiveIpFillPct($svc);
        $need = (int) max(1, (int) ceil($lim * $ipTh / 100));
        $minD = max(1, (int) $this->settings->get('alert_ip_warn_min_distinct', 3));
        $needE = max($need, $minD);
        if ($nIp < $needE) {
            Cache::forget('svp_ip_hyst:'.(int) $svc->id);

            return;
        }
        if (! $this->ipAlertHysteresisAllow((int) $svc->id, $nIp, $needE, $lim)) {
            return;
        }
        if ($this->ipAlertCooldownActive((int) $svc->id)) {
            return;
        }
        $bucketKey = 'svc'.(int) $svc->id.':ip:'.$lim.':'.$ipTh.':m'.$minD;
        if ($this->claimBucket($bucketKey)) {
            $this->notifyUser->sendToUser(
                $user,
                '👥 سرویس «'.(string) ($svc->remark ?: $svc->email).'» به '.$nIp.' اتصال هم‌زمان رسید (سقف '.$lim.').'
            );
            Cache::put('svp_ip_cooldown:'.(int) $svc->id, 1, now()->addHours(6));
        }
    }

    protected function ipAlertHysteresisAllow(int $serviceId, int $nIp, int $needE, int $lim): bool
    {
        $key = 'svp_ip_hyst:'.$serviceId;
        $prev = (int) Cache::get($key, 0);
        Cache::put($key, $nIp, now()->addHours(2));
        if ($prev < 1) {
            return $nIp >= $needE;
        }

        return $nIp >= $prev && $nIp >= (int) ceil($lim * 0.95);
    }

    protected function ipAlertCooldownActive(int $serviceId): bool
    {
        return Cache::has('svp_ip_cooldown:'.$serviceId);
    }

    protected function maybeFillIpFromLog(object $svc): void
    {
        if (! Schema::hasTable('svp_service_ip_log') || ! Schema::hasColumn('svp_services', 'last_ip')) {
            return;
        }
        if (! empty($svc->last_ip)) {
            return;
        }
        $ip = DB::table('svp_service_ip_log')
            ->where('service_id', (int) $svc->id)
            ->orderByDesc('id')
            ->value('ip');
        if (is_string($ip) && $ip !== '') {
            DB::table('svp_services')->where('id', (int) $svc->id)->update(['last_ip' => $ip]);
        }
    }

    protected function maybeTrafficStaleAlert(object $svc, SvpUser $user): void
    {
        $staleDays = max(1, (int) $this->settings->get('traffic_stale_days', 7));
        if (! Schema::hasColumn('svp_services', 'traffic_synced_at')) {
            return;
        }
        $synced = $svc->traffic_synced_at ?? null;
        if ($synced === null) {
            return;
        }
        $ts = strtotime((string) $synced.' UTC');
        if ($ts === false || (time() - $ts) < ($staleDays * 86400)) {
            return;
        }
        $key = 'svc'.(int) $svc->id.':stale_traffic';
        if ($this->claimBucket($key)) {
            $this->notifyUser->sendToUser(
                $user,
                '⚠️ آمار ترافیک سرویس «'.(string) ($svc->remark ?: $svc->email).'» '.$staleDays.' روز به‌روز نشده است.'
            );
        }
    }

    protected function alertsEnabled(object $svc): bool
    {
        if (isset($svc->alerts_enabled) && (int) $svc->alerts_enabled === 0) {
            return false;
        }

        return true;
    }

    protected function coversExpiredByPurge(object $svc): bool
    {
        if (empty($svc->expires_at)) {
            return false;
        }
        $exp = strtotime((string) $svc->expires_at.' UTC');
        if ($exp === false || $exp >= time()) {
            return false;
        }
        if (! $this->purge->isEnabled()) {
            return false;
        }
        $graceDays = $this->purge->effectiveGraceDays();

        return (time() - $exp) > ($graceDays * 86400);
    }

    protected function claimBucket(string $key): bool
    {
        return $this->dedup->claim('expiry', $key, 90);
    }
}
