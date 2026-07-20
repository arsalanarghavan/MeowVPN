<?php

namespace App\Modules\XrayCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InboundAdminService
{
    public function __construct(
        protected NativeXrayProvisioner $provisioner,
        protected NodeAgentClient $agent,
        protected LocalNodeService $localNode,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function updateLocalSettings(array $data): array
    {
        return $this->localNode->updateLocalNode($data);
    }

    /** @param  array<string, mixed>  $data */
    public function saveInbound(array $data, int $id = 0): array
    {
        if (! Schema::hasTable('svp_xray_inbounds')) {
            return svp_err('xray_tables_missing');
        }
        $nodeId = $this->localNode->localNodeId();
        if ($nodeId < 1) {
            return svp_err('local_node_missing');
        }
        $tag = trim((string) ($data['tag'] ?? ''));
        if ($tag === '') {
            return svp_err('tag_required');
        }
        $row = [
            'node_id' => $nodeId,
            'tag' => $tag,
            'remark' => trim((string) ($data['remark'] ?? '')),
            'protocol' => strtolower(trim((string) ($data['protocol'] ?? 'vless'))),
            'port' => max(1, (int) ($data['port'] ?? 443)),
            'settings_json' => $this->jsonField($data['settings_json'] ?? $data['settings'] ?? null),
            'stream_settings_json' => $this->jsonField($data['stream_settings_json'] ?? $data['stream_settings'] ?? null),
            'sniffing_json' => $this->jsonField($data['sniffing_json'] ?? $data['sniffing'] ?? null),
            'active' => ! isset($data['active']) || ! empty($data['active']) ? 1 : 0,
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('svp_xray_inbounds')->where('id', $id)->update($row);
            $this->provisioner->pushLocalConfig();

            return svp_ok(['id' => $id]);
        }
        $row['created_at'] = now();
        $newId = (int) DB::table('svp_xray_inbounds')->insertGetId($row);
        $this->provisioner->pushLocalConfig();

        return svp_ok(['id' => $newId]);
    }

    public function deleteInbound(int $id): array
    {
        $inbound = DB::table('svp_xray_inbounds')->where('id', $id)->first();
        if (! $inbound) {
            return svp_err('not_found');
        }
        DB::table('svp_xray_inbounds')->where('id', $id)->delete();
        DB::table('svp_xray_hosts')->where('inbound_id', $id)->delete();
        $this->provisioner->pushLocalConfig();

        return svp_ok();
    }

    /** @param  array<string, mixed>  $data */
    public function saveHost(array $data, int $id = 0): array
    {
        if (! Schema::hasTable('svp_xray_hosts')) {
            return svp_err('xray_tables_missing');
        }
        $inboundId = max(1, (int) ($data['inbound_id'] ?? 0));
        $row = [
            'inbound_id' => $inboundId,
            'remark' => trim((string) ($data['remark'] ?? '')),
            'address' => trim((string) ($data['address'] ?? '')),
            'port' => max(0, (int) ($data['port'] ?? 0)),
            'sni' => trim((string) ($data['sni'] ?? '')),
            'host' => trim((string) ($data['host'] ?? '')),
            'path' => trim((string) ($data['path'] ?? '')),
            'fingerprint' => trim((string) ($data['fingerprint'] ?? '')),
            'allow_insecure' => ! empty($data['allow_insecure']) ? 1 : 0,
            'priority' => max(0, (int) ($data['priority'] ?? 0)),
            'active' => ! isset($data['active']) || ! empty($data['active']) ? 1 : 0,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('svp_xray_hosts')->where('id', $id)->update($row);

            return svp_ok(['id' => $id]);
        }
        $row['created_at'] = now();
        $newId = (int) DB::table('svp_xray_hosts')->insertGetId($row);

        return svp_ok(['id' => $newId]);
    }

    public function deleteHost(int $id): array
    {
        DB::table('svp_xray_hosts')->where('id', $id)->delete();

        return svp_ok();
    }

    public function restartLocal(): array
    {
        $node = $this->localNode->localNode();
        if (! $node) {
            return svp_err('local_node_missing');
        }
        $push = $this->provisioner->pushLocalConfig();
        if (empty($push['ok'])) {
            return svp_err('config_push_failed', ['detail' => $push['detail'] ?? null]);
        }

        return $this->agent->restart($node);
    }

    public function localHealth(): array
    {
        $nodeId = $this->localNode->localNodeId();
        if ($nodeId < 1) {
            return svp_err('local_node_missing');
        }
        $node = $this->localNode->localNode();
        $health = $this->agent->health($node);
        if (! empty($health['ok'])) {
            DB::table('svp_xray_nodes')->where('id', $nodeId)->update([
                'last_health_at' => now(),
                'last_health_status' => (string) ($health['status'] ?? 'ok'),
            ]);
        }

        return $health;
    }

    /** @return array<string, mixed> */
    public function localOverview(): array
    {
        $node = $this->localNode->localNode();
        if (! $node) {
            return svp_err('local_node_missing');
        }
        $health = $this->agent->health($node);
        $inboundCount = Schema::hasTable('svp_xray_inbounds')
            ? (int) DB::table('svp_xray_inbounds')->where('node_id', (int) $node->id)->where('active', 1)->count()
            : 0;
        $clientCount = Schema::hasTable('svp_services')
            ? (int) DB::table('svp_services')->where('panel_driver', 'native')->whereNull('deleted_at')->count()
            : 0;

        return svp_ok([
            'node' => (array) $node,
            'health' => $health,
            'inbound_count' => $inboundCount,
            'client_count' => $clientCount,
            'agent_url' => $this->localNode->agentUrl(),
        ]);
    }

    protected function jsonField(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return null;
    }
}
