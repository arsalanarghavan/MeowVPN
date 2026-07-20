<?php

namespace App\Modules\XrayCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SubscriptionUriBuilder
{
    /** @return list<string> */
    public function buildForService(object $svc): array
    {
        if ((string) ($svc->panel_driver ?? '') !== 'native') {
            return [];
        }
        $inboundId = (int) ($svc->inbound_id ?? 0);
        if ($inboundId < 1 || ! Schema::hasTable('svp_xray_inbounds')) {
            return [];
        }
        $inbound = DB::table('svp_xray_inbounds')->where('id', $inboundId)->first();
        if (! $inbound) {
            return [];
        }

        $uuid = trim((string) ($svc->xui_client_uuid ?? $svc->xui_client_id ?? ''));
        if ($uuid === '') {
            return [];
        }

        $hosts = DB::table('svp_xray_hosts')
            ->where('inbound_id', $inboundId)
            ->where('active', 1)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        if ($hosts->isEmpty()) {
            $node = DB::table('svp_xray_nodes')->where('id', (int) $inbound->node_id)->first();
            if ($node && trim((string) $node->public_ip) !== '') {
                $hosts = collect([(object) [
                    'address' => $node->public_ip,
                    'port' => (int) $inbound->port,
                    'sni' => '',
                    'host' => '',
                    'path' => '',
                    'fingerprint' => '',
                    'remark' => (string) ($inbound->remark ?? ''),
                ]]);
            } else {
                return [];
            }
        }

        $uris = [];
        $remark = rawurlencode((string) ($svc->remark ?? $inbound->remark ?? 'MeowVPN'));
        $protocol = strtolower((string) $inbound->protocol);
        $settings = $this->decodeJson((string) ($inbound->settings_json ?? ''));
        $stream = $this->decodeJson((string) ($inbound->stream_settings_json ?? ''));

        foreach ($hosts as $host) {
            $address = trim((string) ($host->address ?? ''));
            if ($address === '') {
                continue;
            }
            $port = (int) ($host->port ?? 0);
            if ($port < 1) {
                $port = (int) $inbound->port;
            }

            $uri = match ($protocol) {
                'vless' => $this->buildVless($uuid, $address, $port, $stream, $settings, $remark, $host),
                'vmess' => $this->buildVmess($uuid, $address, $port, $stream, $settings, $remark, $host),
                'trojan' => $this->buildTrojan($uuid, $address, $port, $stream, $settings, $remark, $host),
                'shadowsocks', 'ss' => $this->buildShadowsocks($uuid, $address, $port, $settings, $remark, $host),
                default => null,
            };
            if (is_string($uri) && $uri !== '') {
                $uris[] = $uri;
            }
        }

        return array_values(array_unique($uris));
    }

    /** @param  array<string, mixed>  $stream  */
    /** @param  array<string, mixed>  $settings  */
    protected function buildVless(string $uuid, string $address, int $port, array $stream, array $settings, string $remark, object $host): string
    {
        $params = ['encryption' => 'none'];
        $network = (string) ($stream['network'] ?? 'tcp');
        $params['type'] = $network;
        $security = (string) ($stream['security'] ?? 'none');
        $params['security'] = $security;

        if ($security === 'reality') {
            $reality = is_array($stream['realitySettings'] ?? null) ? $stream['realitySettings'] : [];
            $params['pbk'] = (string) ($reality['publicKey'] ?? '');
            $params['sid'] = (string) ($reality['shortIds'][0] ?? $reality['shortId'] ?? '');
            $params['fp'] = trim((string) ($host->fingerprint ?? '')) ?: 'chrome';
            $params['sni'] = trim((string) ($host->sni ?? '')) ?: (string) ($reality['serverNames'][0] ?? '');
            if (! empty($settings['flow'])) {
                $params['flow'] = (string) $settings['flow'];
            }
        } elseif ($security === 'tls') {
            $params['sni'] = trim((string) ($host->sni ?? ''));
            $params['fp'] = trim((string) ($host->fingerprint ?? ''));
        }

        if ($network === 'ws') {
            $ws = is_array($stream['wsSettings'] ?? null) ? $stream['wsSettings'] : [];
            $params['path'] = trim((string) ($host->path ?? '')) ?: (string) ($ws['path'] ?? '/');
            $params['host'] = trim((string) ($host->host ?? ''));
        }

        $query = http_build_query(array_filter($params, fn ($v) => $v !== '' && $v !== null));

        return 'vless://'.$uuid.'@'.$address.':'.$port.'?'.$query.'#'.$remark;
    }

    /** @param  array<string, mixed>  $stream  */
    /** @param  array<string, mixed>  $settings  */
    protected function buildVmess(string $uuid, string $address, int $port, array $stream, array $settings, string $remark, object $host): string
    {
        $network = (string) ($stream['network'] ?? 'tcp');
        $tls = (string) ($stream['security'] ?? 'none') === 'tls' ? 'tls' : '';
        $payload = [
            'v' => '2',
            'ps' => urldecode($remark),
            'add' => $address,
            'port' => (string) $port,
            'id' => $uuid,
            'aid' => '0',
            'net' => $network,
            'type' => 'none',
            'host' => trim((string) ($host->host ?? '')),
            'path' => trim((string) ($host->path ?? '')),
            'tls' => $tls,
            'sni' => trim((string) ($host->sni ?? '')),
        ];

        return 'vmess://'.base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /** @param  array<string, mixed>  $stream  */
    /** @param  array<string, mixed>  $settings  */
    protected function buildTrojan(string $password, string $address, int $port, array $stream, array $settings, string $remark, object $host): string
    {
        $params = [];
        if ((string) ($stream['security'] ?? '') === 'tls') {
            $params['security'] = 'tls';
            $params['sni'] = trim((string) ($host->sni ?? ''));
        }
        $query = $params !== [] ? '?'.http_build_query($params) : '';

        return 'trojan://'.$password.'@'.$address.':'.$port.$query.'#'.$remark;
    }

    /** @param  array<string, mixed>  $settings  */
    protected function buildShadowsocks(string $uuid, string $address, int $port, array $settings, string $remark, object $host): string
    {
        $method = (string) ($settings['method'] ?? 'aes-256-gcm');
        $password = (string) ($settings['password'] ?? $uuid);
        $userInfo = base64_encode($method.':'.$password);
        $encoded = rtrim(strtr(base64_encode($userInfo.'@'.$address.':'.$port), '+/', '-_'), '=');

        return 'ss://'.$encoded.'#'.$remark;
    }

    /** @return array<string, mixed> */
    protected function decodeJson(string $json): array
    {
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
