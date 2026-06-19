<?php

namespace App\Modules\XuiPanel\Services;

use App\Support\Xui\InboundTraffic;
use Illuminate\Support\Facades\DB;

class PanelAdminService
{
    public function __construct(
        protected XuiClient $xui,
        protected ConfigsSyncService $configs,
    ) {}

    /** @return array<string, mixed> */
    public function inboundsList(int $panelId): array
    {
        if ($panelId < 1) {
            return ['ok' => false, 'message' => 'invalid_panel'];
        }

        return $this->xui->runWithPanel($panelId, function () {
            if (! $this->xui->loginWithRetries()) {
                return ['ok' => false, 'message' => 'login_fail'];
            }
            $raw = $this->xui->inboundsList();
            if (! is_array($raw)) {
                return ['ok' => false, 'message' => 'inbounds_fetch_failed'];
            }
            $list = [];
            foreach ($raw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $list[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'remark' => (string) ($row['remark'] ?? ''),
                    'port' => (int) ($row['port'] ?? 0),
                    'protocol' => (string) ($row['protocol'] ?? ''),
                ];
            }

            return ['ok' => true, 'data' => ['inbounds' => $list]];
        });
    }

    /** @return array<string, mixed> */
    public function inboundClients(int $panelId, int $inboundId): array
    {
        if ($inboundId < 1) {
            return ['ok' => false, 'message' => 'invalid_inbound'];
        }
        $svcPanel = $panelId > 0 ? $panelId : 1;

        return $this->xui->runWithPanel($panelId, function () use ($inboundId, $svcPanel) {
            if (! $this->xui->loginWithRetries()) {
                return ['ok' => false, 'message' => 'login_fail'];
            }
            $inb = $this->xui->inboundGet($inboundId);
            if (! is_array($inb)) {
                return ['ok' => false, 'message' => 'inbound_not_found'];
            }
            $settings = $inb['settings'] ?? '';
            $dec = is_string($settings) ? json_decode($settings, true) : (is_array($settings) ? $settings : []);
            $clients = [];
            if (is_array($dec) && ! empty($dec['clients']) && is_array($dec['clients'])) {
                foreach ($dec['clients'] as $c) {
                    if (! is_array($c) || empty($c['email'])) {
                        continue;
                    }
                    $email = trim((string) $c['email']);
                    $svc = DB::table('svp_services')
                        ->where('inbound_id', $inboundId)
                        ->where('panel_id', $svcPanel)
                        ->where('email', $email)
                        ->whereNull('deleted_at')
                        ->first();
                    $totalBytes = InboundTraffic::totalgbToBytes($c['totalGB'] ?? 0);
                    $clients[] = [
                        'email' => $email,
                        'enable' => ! empty($c['enable']),
                        'total_gb' => $totalBytes > 0 ? (int) round($totalBytes / 1073741824) : 0,
                        'expiry_ms' => (int) ($c['expiryTime'] ?? 0),
                        'service_id' => $svc ? (int) $svc->id : 0,
                        'user_id' => $svc ? (int) $svc->user_id : 0,
                    ];
                }
            }

            return ['ok' => true, 'data' => ['clients' => $clients]];
        });
    }

    /** @return array<string, mixed> */
    public function portalPayload(int $serviceId, int $panelId, int $inboundId, string $email): array
    {
        if ($serviceId > 0) {
            $svc = DB::table('svp_services')->where('id', $serviceId)->first();
            if (! $svc) {
                return ['ok' => false, 'message' => 'service_not_found'];
            }
            $panelId = (int) $svc->panel_id;
            $inboundId = (int) $svc->inbound_id;
            $email = (string) $svc->email;
        }
        if ($panelId < 1 || $inboundId < 1 || trim($email) === '') {
            return ['ok' => false, 'message' => 'bad_params'];
        }
        $panel = DB::table('svp_panels')->where('id', $panelId)->first();
        $subBase = $panel ? rtrim((string) ($panel->subscription_public_base ?? ''), '/') : '';

        return [
            'ok' => true,
            'data' => [
                'panel_id' => $panelId,
                'inbound_id' => $inboundId,
                'email' => $email,
                'subscription_url' => $subBase !== '' ? $subBase.'/sub/'.rawurlencode($email) : '',
                'lines' => [],
            ],
        ];
    }
}
