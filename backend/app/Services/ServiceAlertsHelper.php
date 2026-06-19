<?php

namespace App\Services;

use App\Modules\XuiPanel\Services\XuiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServiceAlertsHelper
{
    public function __construct(
        protected NotifySettings $notify,
        protected ?XuiClient $xui = null,
    ) {}

    public function effectiveIpFillPct(object $svc): int
    {
        $sched = $this->parseAlertSchedule($svc);
        if (isset($sched['ip_fill_pct'])) {
            $p = (int) $sched['ip_fill_pct'];
            if ($p >= 50 && $p <= 100) {
                return $p;
            }
        }
        if (isset($svc->alert_ip_fill_pct) && $svc->alert_ip_fill_pct !== null && (string) $svc->alert_ip_fill_pct !== '') {
            $p = (int) $svc->alert_ip_fill_pct;
            if ($p >= 50 && $p <= 100) {
                return $p;
            }
        }

        return 90;
    }

    public function effectiveLowTrafficPct(object $svc): int
    {
        $sched = $this->parseAlertSchedule($svc);
        if (isset($sched['low_traffic_pct'])) {
            $p = (int) $sched['low_traffic_pct'];
            if ($p >= 1 && $p <= 99) {
                return $p;
            }
        }
        if (isset($svc->alert_low_pct) && $svc->alert_low_pct !== null && (string) $svc->alert_low_pct !== '') {
            $p = (int) $svc->alert_low_pct;
            if ($p >= 1 && $p <= 99) {
                return $p;
            }
        }

        return $this->notify->globalLowTrafficPct();
    }

    /** @return list<int> */
    public function effectiveExpiryDays(object $svc): array
    {
        $sched = $this->parseAlertSchedule($svc);
        if (isset($sched['expiry_days']) && is_array($sched['expiry_days'])) {
            $out = [];
            foreach ($sched['expiry_days'] as $x) {
                $d = (int) $x;
                if ($d >= -3650 && $d <= 3650) {
                    $out[] = $d;
                }
            }
            $out = array_values(array_unique($out));
            if ($out !== []) {
                return $out;
            }
        }
        if (isset($svc->alert_expiry_days) && trim((string) $svc->alert_expiry_days) !== '') {
            $out = [];
            foreach (explode(',', (string) $svc->alert_expiry_days) as $part) {
                $d = (int) trim($part);
                if ($d >= -3650 && $d <= 3650) {
                    $out[] = $d;
                }
            }
            $out = array_values(array_unique($out));
            if ($out !== []) {
                return $out;
            }
        }

        return $this->notify->globalExpiryDays();
    }

    public function clientLimitIp(object $svc): int
    {
        $limit = (int) ($svc->panel_limit_ip ?? $svc->limit_ip ?? 0);
        if ($limit > 0) {
            return $limit;
        }

        return 0;
    }

    public function clientIpCount(object $svc, bool $tryLiveApi = true): int
    {
        $email = trim((string) ($svc->email ?? ''));
        $panelId = max(1, (int) ($svc->panel_id ?? 1));

        if ($tryLiveApi && $email !== '' && $this->xui !== null && svp_modules()->isEnabled('xui_panel')) {
            $live = $this->liveClientIpCount($panelId, $email);
            if ($live > 0) {
                return $live;
            }
        }

        if (Schema::hasTable('svp_panel_inbound_clients')) {
            $row = DB::table('svp_panel_inbound_clients')
                ->where('panel_id', $panelId)
                ->where('inbound_id', (int) ($svc->inbound_id ?? 0))
                ->where('email', $email)
                ->first();
            if ($row && ! empty($row->client_ips_json)) {
                $ips = json_decode((string) $row->client_ips_json, true);
                if (is_array($ips)) {
                    return count($ips);
                }
            }
        }
        if (Schema::hasTable('svp_service_ip_log')) {
            return (int) DB::table('svp_service_ip_log')
                ->where('service_id', (int) $svc->id)
                ->distinct()
                ->count('ip');
        }

        return 0;
    }

    protected function liveClientIpCount(int $panelId, string $email): int
    {
        if ($this->xui === null) {
            return 0;
        }
        $panel = DB::table('svp_panels')->where('id', $panelId)->first();
        if (! $panel) {
            return 0;
        }

        return (int) $this->xui->runWithPanel($panelId, function (XuiClient $client) use ($email) {
            if (! $client->loginWithRetries(3, 200000)) {
                return 0;
            }
            $json = $client->clientIps($email);

            return count($client->parseClientIpsResponse($json, 100));
        }, (array) $panel);
    }

    public function volumeEnabled(object $svc): bool
    {
        if (isset($svc->alerts_volume)) {
            return (int) $svc->alerts_volume === 1;
        }
        if (isset($svc->alert_volume_enabled) && (int) $svc->alert_volume_enabled === 0) {
            return false;
        }

        return ! isset($svc->alerts_enabled) || (int) $svc->alerts_enabled === 1;
    }

    public function usersAlertEnabled(object $svc): bool
    {
        if (isset($svc->alerts_users)) {
            return (int) $svc->alerts_users === 1;
        }
        if (isset($svc->alert_users_enabled) && (int) $svc->alert_users_enabled === 0) {
            return false;
        }

        return ! isset($svc->alerts_enabled) || (int) $svc->alerts_enabled === 1;
    }

    public function expiryAlertEnabled(object $svc): bool
    {
        if (isset($svc->alerts_expiry)) {
            return (int) $svc->alerts_expiry === 1;
        }
        if (isset($svc->alert_expiry_enabled) && (int) $svc->alert_expiry_enabled === 0) {
            return false;
        }

        return ! isset($svc->alerts_enabled) || (int) $svc->alerts_enabled === 1;
    }

    /** @return array<string, mixed> */
    protected function parseAlertSchedule(object $svc): array
    {
        if (empty($svc->alert_schedule_json)) {
            return [];
        }
        $dec = json_decode((string) $svc->alert_schedule_json, true);

        return is_array($dec) ? $dec : [];
    }
}
