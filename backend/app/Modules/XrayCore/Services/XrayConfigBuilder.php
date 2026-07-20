<?php

namespace App\Modules\XrayCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class XrayConfigBuilder
{
    /** @return array<string, mixed> */
    public function buildForNode(int $nodeId): array
    {
        $node = DB::table('svp_xray_nodes')->where('id', $nodeId)->first();
        if (! $node) {
            return $this->defaultBase();
        }

        $base = $this->defaultBase();
        if (! empty($node->base_config_json)) {
            $decoded = json_decode((string) $node->base_config_json, true);
            if (is_array($decoded)) {
                $base = $decoded;
            }
        }

        $inbounds = $this->buildInbounds($nodeId);
        $base['inbounds'] = $inbounds;

        if (! isset($base['api']) || ! is_array($base['api'])) {
            $base['api'] = [
                'tag' => 'api',
                'services' => ['HandlerService', 'StatsService'],
            ];
        }
        if (! isset($base['stats']) || ! is_array($base['stats'])) {
            $base['stats'] = [];
        }
        if (! isset($base['policy']) || ! is_array($base['policy'])) {
            $base['policy'] = [
                'levels' => ['0' => ['statsUserUplink' => true, 'statsUserDownlink' => true]],
                'system' => ['statsInboundUplink' => true, 'statsInboundDownlink' => true],
            ];
        }

        return $base;
    }

    /** @return list<array<string, mixed>> */
    protected function buildInbounds(int $nodeId): array
    {
        if (! Schema::hasTable('svp_xray_inbounds')) {
            return [];
        }

        $rows = DB::table('svp_xray_inbounds')
            ->where('node_id', $nodeId)
            ->where('active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $settings = $this->decodeJson((string) ($row->settings_json ?? ''));
            $clients = $this->clientsForInbound((int) $row->id);
            $settings['clients'] = $clients;

            $inbound = [
                'tag' => (string) $row->tag,
                'port' => (int) $row->port,
                'protocol' => strtolower((string) $row->protocol),
                'settings' => $settings,
            ];

            $stream = $this->decodeJson((string) ($row->stream_settings_json ?? ''));
            if ($stream !== []) {
                $inbound['streamSettings'] = $stream;
            }
            $sniff = $this->decodeJson((string) ($row->sniffing_json ?? ''));
            if ($sniff !== []) {
                $inbound['sniffing'] = $sniff;
            }

            $out[] = $inbound;
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    protected function clientsForInbound(int $inboundDbId): array
    {
        if (! Schema::hasTable('svp_services')) {
            return [];
        }

        $rows = DB::table('svp_services')
            ->where('panel_driver', 'native')
            ->where('inbound_id', $inboundDbId)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('client_enabled')->orWhere('client_enabled', 1);
            })
            ->get();

        $clients = [];
        foreach ($rows as $svc) {
            $uuid = trim((string) ($svc->xui_client_uuid ?? $svc->xui_client_id ?? ''));
            if ($uuid === '') {
                continue;
            }
            $email = (string) ($svc->email ?? '');
            $limitIp = (int) ($svc->panel_limit_ip ?? 0);
            if ($limitIp < 1) {
                $limitIp = max(0, (int) (DB::table('svp_settings')->where('key_name', 'default_concurrent_users')->value('value') ?? 2));
            }

            $client = [
                'id' => $uuid,
                'email' => $email,
                'enable' => true,
                'limitIp' => $limitIp,
            ];

            $flow = trim((string) ($svc->remark ?? ''));
            if ($flow !== '' && str_contains($flow, 'xtls')) {
                $client['flow'] = 'xtls-rprx-vision';
            }

            $clients[] = $client;
        }

        return $clients;
    }

    /** @return array<string, mixed> */
    protected function defaultBase(): array
    {
        $fromEnv = trim((string) config('svp.xray_base_config_json', ''));
        if ($fromEnv !== '') {
            $decoded = json_decode($fromEnv, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [
            'log' => ['loglevel' => 'warning'],
            'routing' => [
                'domainStrategy' => 'AsIs',
                'rules' => [
                    ['type' => 'field', 'inboundTag' => ['api'], 'outboundTag' => 'api'],
                ],
            ],
            'inbounds' => [
                [
                    'tag' => 'api',
                    'listen' => '127.0.0.1',
                    'port' => 10085,
                    'protocol' => 'dokodemo-door',
                    'settings' => ['address' => '127.0.0.1'],
                ],
            ],
            'outbounds' => [
                ['protocol' => 'freedom', 'tag' => 'direct'],
                ['protocol' => 'blackhole', 'tag' => 'blocked'],
                ['protocol' => 'freedom', 'tag' => 'api'],
            ],
        ];
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

    public static function generateUuid(): string
    {
        return (string) Str::uuid();
    }
}
