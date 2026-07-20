<?php

namespace App\Modules\XuiPanel\Services;

use App\Models\SvpUser;
use App\Modules\Core\Services\UserBotNotifyService;
use App\Modules\L2tp\Services\L2tpProvisionerService;
use App\Services\NotificationDedupService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeExpiredService
{
    public const BATCH_LIMIT = 30;

    public function __construct(
        protected SettingsStore $settings,
        protected XuiClient $xui,
        protected UserBotNotifyService $notify,
        protected NotificationDedupService $dedup,
    ) {}

    /** @return array{purged:int,warned:int,failed:int,grace:int,source:string} */
    public function runBatch(int $limit = self::BATCH_LIMIT, string $source = 'cron', bool $ignoreEnabled = false): array
    {
        $stats = [
            'purged' => 0,
            'warned' => 0,
            'failed' => 0,
            'grace' => $this->effectiveGraceDays(),
            'source' => $source,
        ];
        if (! $this->settings->get('enabled', true) || ! Schema::hasTable('svp_services')) {
            return $stats;
        }
        if (! $ignoreEnabled && ! $this->isEnabled()) {
            return $stats;
        }

        $grace = $stats['grace'];
        $warnDays = $this->effectiveWarnDays();
        $notify = $this->notifyUserEnabled();

        foreach ($this->expiredXrayServiceRows($limit) as $svc) {
            $sid = (int) ($svc->id ?? 0);
            if ($sid < 1 || empty($svc->expires_at)) {
                continue;
            }
            if (L2tpProvisionerService::isL2tp($svc)) {
                continue;
            }
            $status = $this->servicePurgeStatus($svc, $grace);
            if ($status['status'] === 'not_expired') {
                continue;
            }
            $daysSince = (int) $status['days_since_expiry'];
            $daysUntil = (int) $status['days_until_purge'];

            if ($daysUntil <= 0) {
                if ($notify && $this->maybeNotifyPurge($svc, 0, $grace, $daysSince, true)) {
                    $stats['warned']++;
                }
                if ($this->purgeService($svc, $grace, $daysSince, 'system')) {
                    $stats['purged']++;
                } else {
                    $stats['failed']++;
                }

                continue;
            }

            if (! $notify || ! in_array($daysUntil, $warnDays, true)) {
                continue;
            }
            if ($this->maybeNotifyPurge($svc, $daysUntil, $grace, $daysSince, false)) {
                $stats['warned']++;
            }
        }

        $this->storeLastRun($stats, $source);

        return $stats;
    }

    /** @return array{purged:int,failed:int,grace:int} */
    public function purgeReadyBatch(int $limit = 50): array
    {
        $grace = $this->effectiveGraceDays();
        $stats = ['purged' => 0, 'failed' => 0, 'grace' => $grace];
        $lim = max(1, min(100, $limit));
        $scanned = 0;
        foreach ($this->expiredXrayServiceRows(100) as $svc) {
            if ($scanned >= $lim) {
                break;
            }
            $scanned++;
            if (L2tpProvisionerService::isL2tp($svc)) {
                continue;
            }
            $status = $this->servicePurgeStatus($svc, $grace);
            if ($status['status'] !== 'ready') {
                continue;
            }
            if ($this->purgeService($svc, $grace, (int) $status['days_since_expiry'], 'admin')) {
                $stats['purged']++;
            } else {
                $stats['failed']++;
            }
        }

        return $stats;
    }

    public function isEnabled(): bool
    {
        $v = $this->settings->get('purge_expired_enabled');
        if ($v === null) {
            $v = $this->settings->get('purge_expired.purge_expired_enabled');
        }

        return (bool) ($v ?? false);
    }

    public function effectiveGraceDays(): int
    {
        $days = $this->settings->get('purge_expired_grace_days');
        if ($days === null) {
            $days = $this->settings->get('purge_expired.purge_expired_grace_days');
        }
        if ($days === null) {
            $hours = (int) $this->settings->get('purge_expired_grace_hours', 0);
            if ($hours > 0) {
                $days = (int) max(1, ceil($hours / 24));
            }
        }

        return max(1, min(365, (int) ($days ?? 7)));
    }

    /** @return list<int> */
    public function effectiveWarnDays(): array
    {
        $raw = $this->settings->get('purge_expired_warn_days', [7, 3, 1, 0]);
        if (! is_array($raw)) {
            $raw = is_string($raw) && $raw !== ''
                ? array_map('intval', explode(',', $raw))
                : [7, 3, 1, 0];
        }
        $out = [];
        foreach ($raw as $d) {
            $n = (int) $d;
            if ($n >= 0 && $n <= 365) {
                $out[] = $n;
            }
        }
        $out = array_values(array_unique($out));

        return $out !== [] ? $out : [7, 3, 1, 0];
    }

    public function notifyUserEnabled(): bool
    {
        $v = $this->settings->get('purge_expired_notify_user');
        if ($v === null) {
            $v = $this->settings->get('purge_expired.purge_expired_notify_user', true);
        }

        return (bool) $v;
    }

    /** @return array{days_since_expiry:int,days_until_purge:int,status:string} */
    public function servicePurgeStatus(object $svc, ?int $grace = null): array
    {
        $grace = max(1, min(365, $grace ?? $this->effectiveGraceDays()));
        if (empty($svc->expires_at)) {
            return ['days_since_expiry' => 0, 'days_until_purge' => $grace, 'status' => 'not_expired'];
        }
        $expTs = strtotime((string) $svc->expires_at.' UTC');
        if ($expTs === false || $expTs >= time()) {
            return ['days_since_expiry' => 0, 'days_until_purge' => $grace, 'status' => 'not_expired'];
        }
        $daysSince = (int) floor((time() - $expTs) / 86400);
        $daysUntil = $grace - $daysSince;

        return [
            'days_since_expiry' => $daysSince,
            'days_until_purge' => $daysUntil,
            'status' => $daysUntil <= 0 ? 'ready' : 'in_grace',
        ];
    }

    /** @return list<object> */
    protected function expiredXrayServiceRows(int $limit): array
    {
        $lim = max(1, min(100, $limit));

        return DB::table('svp_services')
            ->whereNull('deleted_at')
            ->where('inbound_id', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->where(function ($q) {
                $q->whereNull('service_type')
                    ->orWhere('service_type', '')
                    ->orWhere('service_type', 'xray');
            })
            ->where(function ($q) {
                $q->whereNull('service_type')->orWhere('service_type', '!=', 'l2tp');
            })
            ->orderBy('expires_at')
            ->limit($lim)
            ->get()
            ->all();
    }

    protected function purgeService(object $svc, int $graceDays, int $daysSinceExpiry, string $actorKind): bool
    {
        $sid = (int) ($svc->id ?? 0);
        if ($sid < 1) {
            return false;
        }
        if ((int) ($svc->inbound_id ?? 0) > 0 && trim((string) ($svc->email ?? '')) !== '') {
            $this->xui->deleteClient([], $sid);
        }
        DB::table('svp_services')->where('id', $sid)->update(['deleted_at' => now()]);

        return true;
    }

    protected function maybeNotifyPurge(object $svc, int $daysUntilPurge, int $graceDays, int $daysSinceExpiry, bool $isPurgeDay): bool
    {
        $sid = (int) ($svc->id ?? 0);
        if ($sid < 1) {
            return false;
        }
        $bucketKey = $isPurgeDay
            ? 'svc'.$sid.':purge_day'
            : 'svc'.$sid.':purge_warn:'.$daysUntilPurge;
        if (! $this->dedup->claim('purge_expired', $bucketKey, 90)) {
            return false;
        }
        $user = SvpUser::query()->find((int) ($svc->user_id ?? 0));
        if (! $user || (string) $user->status !== 'approved') {
            return false;
        }
        $label = (string) ($svc->remark ?: $svc->email);
        if ($isPurgeDay) {
            $text = '🗑️ سرویس «'.$label.'» به‌دلیل انقضا و پایان مهلت '.$graceDays.' روزه حذف شد.';
        } else {
            $text = '⚠️ سرویس «'.$label.'» منقضی شده است. تا '.$daysUntilPurge.' روز دیگر (پس از '.$graceDays.' روز مهلت) حذف می‌شود.';
        }
        $this->notify->sendToUser($user, $text);
        if (Schema::hasColumn('svp_services', 'last_warn_sent_at')) {
            DB::table('svp_services')->where('id', $sid)->update(['last_warn_sent_at' => now()]);
        }

        return true;
    }

    /** @param  array{purged:int,warned:int,failed:int,grace:int,source:string}  $stats */
    protected function storeLastRun(array $stats, string $source): void
    {
        $payload = [
            'at' => time(),
            'purged' => $stats['purged'],
            'warned' => $stats['warned'],
            'failed' => $stats['failed'],
            'grace' => $stats['grace'],
            'source' => $source,
        ];
        if ($source !== 'cron') {
            $prev = $this->settings->get('last_purge_expired_run', []);
            if (is_array($prev)) {
                $payload['purged'] = (int) ($prev['purged'] ?? 0) + $stats['purged'];
                $payload['warned'] = (int) ($prev['warned'] ?? 0) + $stats['warned'];
                $payload['failed'] = (int) ($prev['failed'] ?? 0) + $stats['failed'];
            }
        }
        $this->settings->set('last_purge_expired_run', $payload);
    }
}
