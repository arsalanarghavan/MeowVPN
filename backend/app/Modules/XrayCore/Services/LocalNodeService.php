<?php

namespace App\Modules\XrayCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LocalNodeService
{
    public function agentUrl(): string
    {
        return rtrim(trim((string) config('svp.xray_agent_url', 'http://xray-agent:8444')), '/');
    }

    public function publicIp(): string
    {
        $fromEnv = trim((string) config('svp.xray_public_ip', ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }
        if (Schema::hasTable('svp_settings')) {
            $fromSettings = trim((string) DB::table('svp_settings')->where('key_name', 'xray_public_ip')->value('value'));
            if ($fromSettings !== '') {
                return $fromSettings;
            }
        }

        return '';
    }

    /** Ensure exactly one local node row exists; return its id. */
    public function ensureLocalNode(): int
    {
        if (! Schema::hasTable('svp_xray_nodes')) {
            return 0;
        }

        $existing = DB::table('svp_xray_nodes')->where('is_local', 1)->first();
        if ($existing) {
            $this->syncLocalNodeRow((int) $existing->id);

            return (int) $existing->id;
        }

        $legacy = DB::table('svp_xray_nodes')->orderBy('id')->first();
        if ($legacy) {
            DB::table('svp_xray_nodes')->where('id', (int) $legacy->id)->update([
                'is_local' => 1,
                'is_primary' => 1,
                'label' => 'Bot host (local)',
                'updated_at' => now(),
            ]);
            $this->syncLocalNodeRow((int) $legacy->id);

            return (int) $legacy->id;
        }

        $id = (int) DB::table('svp_xray_nodes')->insertGetId([
            'label' => 'Bot host (local)',
            'public_ip' => $this->publicIp(),
            'agent_url' => $this->agentUrl(),
            'is_primary' => 1,
            'is_local' => 1,
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    public function localNodeId(): int
    {
        if (! Schema::hasTable('svp_xray_nodes')) {
            return 0;
        }
        $id = (int) DB::table('svp_xray_nodes')->where('is_local', 1)->value('id');
        if ($id > 0) {
            return $id;
        }

        return $this->ensureLocalNode();
    }

    /** @return object|null */
    public function localNode(): ?object
    {
        $id = $this->localNodeId();
        if ($id < 1) {
            return null;
        }

        return DB::table('svp_xray_nodes')->where('id', $id)->first();
    }

    /** @param  array<string, mixed>  $data */
    public function updateLocalNode(array $data): array
    {
        $id = $this->ensureLocalNode();
        $updates = ['updated_at' => now()];
        if (isset($data['public_ip'])) {
            $updates['public_ip'] = trim((string) $data['public_ip']);
        }
        if (isset($data['base_config_json'])) {
            $updates['base_config_json'] = $data['base_config_json'];
        }
        if (isset($data['label'])) {
            $updates['label'] = trim((string) $data['label']) ?: 'Bot host (local)';
        }
        DB::table('svp_xray_nodes')->where('id', $id)->update($updates);
        if (! empty($updates['public_ip']) && Schema::hasTable('svp_settings')) {
            DB::table('svp_settings')->updateOrInsert(
                ['key_name' => 'xray_public_ip'],
                ['value' => (string) $updates['public_ip']]
            );
        }

        return svp_ok(['id' => $id]);
    }

    protected function syncLocalNodeRow(int $id): void
    {
        DB::table('svp_xray_nodes')->where('id', '!=', $id)->update(['is_local' => 0]);
        $updates = [
            'agent_url' => $this->agentUrl(),
            'is_local' => 1,
            'is_primary' => 1,
            'updated_at' => now(),
        ];
        $ip = $this->publicIp();
        if ($ip !== '') {
            $updates['public_ip'] = $ip;
        }
        DB::table('svp_xray_nodes')->where('id', $id)->update($updates);
    }
}
