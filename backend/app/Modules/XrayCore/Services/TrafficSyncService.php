<?php

namespace App\Modules\XrayCore\Services;

use App\Modules\XrayCore\DTO\TrafficStats;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TrafficSyncService
{
    public function __construct(
        protected NodeAgentClient $agent,
        protected NativeXrayProvisioner $provisioner,
        protected LocalNodeService $localNode,
    ) {}

    public function syncAll(): array
    {
        if (! Schema::hasTable('svp_xray_nodes') || ! svp_modules()->isEnabled('xray_core')) {
            return ['synced' => 0, 'disabled' => 0, 'failed' => 0];
        }

        $node = $this->localNode->localNode();
        if (! $node) {
            return ['synced' => 0, 'disabled' => 0, 'failed' => 1];
        }

        $stats = ['synced' => 0, 'disabled' => 0, 'failed' => 0];
        $nodeStats = $this->agent->stats($node);
        if (empty($nodeStats['ok'])) {
            return ['synced' => 0, 'disabled' => 0, 'failed' => 1];
        }

        $byEmail = is_array($nodeStats['stats'] ?? null) ? $nodeStats['stats'] : [];
        $services = DB::table('svp_services')
            ->where('panel_driver', 'native')
            ->where('panel_id', (int) $node->id)
            ->whereNull('deleted_at')
            ->get();

        foreach ($services as $svc) {
            $email = (string) ($svc->email ?? '');
            $used = 0;
            if ($email !== '' && isset($byEmail[$email]) && is_array($byEmail[$email])) {
                $used = (int) ($byEmail[$email]['up'] ?? 0) + (int) ($byEmail[$email]['down'] ?? 0);
            }
            DB::table('svp_services')->where('id', (int) $svc->id)->update(['used_traffic' => $used]);
            $stats['synced']++;

            if ($this->shouldDisable($svc, $used)) {
                DB::table('svp_services')->where('id', (int) $svc->id)->update(['client_enabled' => 0]);
                $stats['disabled']++;
            }
        }

        if ($stats['disabled'] > 0) {
            $this->provisioner->pushLocalConfig();
        }

        return $stats;
    }

    /** @param  object  $svc */
    public function syncOne(object $svc): ?TrafficStats
    {
        $node = $this->localNode->localNode();
        if (! $node) {
            return null;
        }
        $result = $this->agent->stats($node);
        if (empty($result['ok'])) {
            return null;
        }
        $email = (string) ($svc->email ?? '');
        $entry = is_array($result['stats'][$email] ?? null) ? $result['stats'][$email] : null;
        if ($entry === null) {
            return new TrafficStats;
        }

        return new TrafficStats(
            upload: (int) ($entry['up'] ?? 0),
            download: (int) ($entry['down'] ?? 0),
        );
    }

    /** @param  object  $svc */
    protected function shouldDisable(object $svc, int $used): bool
    {
        $total = (int) ($svc->total_traffic ?? 0);
        if ($total > 0 && $used >= $total) {
            return true;
        }
        if (! empty($svc->expires_at)) {
            $exp = strtotime((string) $svc->expires_at.' UTC');

            return $exp > 0 && $exp <= time();
        }

        return false;
    }
}
