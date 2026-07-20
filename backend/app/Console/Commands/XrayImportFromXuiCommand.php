<?php

namespace App\Console\Commands;

use App\Modules\XuiPanel\Services\XuiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class XrayImportFromXuiCommand extends Command
{
    protected $signature = 'svp:xray_import_xui
        {--panel-id=1 : Source 3x-ui panel id in svp_panels}
        {--node-id=0 : Target native node id (creates one if 0)}
        {--dry-run : Preview without writing}';

    protected $description = 'Import inbounds from a 3x-ui panel into native Xray tables (one-time cutover helper)';

    public function handle(XuiClient $xui): int
    {
        if (! svp_modules()->isEnabled('xray_core')) {
            $this->error('xray_core module is disabled');

            return self::FAILURE;
        }
        if (! Schema::hasTable('svp_xray_nodes') || ! Schema::hasTable('svp_xray_inbounds')) {
            $this->error('Run migrations first');

            return self::FAILURE;
        }

        $panelId = max(1, (int) $this->option('panel-id'));
        $nodeId = (int) $this->option('node-id');
        $dryRun = (bool) $this->option('dry-run');

        $imported = $xui->runWithPanel($panelId, function () use ($xui, $panelId, $nodeId, $dryRun) {
            if (! $xui->loginWithRetries(5)) {
                $this->error('Panel login failed');

                return -1;
            }
            $list = $xui->inboundsList();
            if (! is_array($list)) {
                $this->error('Could not list inbounds');

                return -1;
            }

            if ($nodeId < 1 && ! $dryRun) {
                $panel = DB::table('svp_panels')->where('id', $panelId)->first();
                $nodeId = (int) DB::table('svp_xray_nodes')->insertGetId([
                    'label' => 'Imported from panel #'.$panelId,
                    'public_ip' => '',
                    'agent_url' => 'https://127.0.0.1:8444',
                    'is_primary' => 1,
                    'active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->info('Created node id='.$nodeId);
            } elseif ($nodeId < 1) {
                $nodeId = 1;
            }

            $count = 0;
            foreach ($list as $inb) {
                if (! is_array($inb)) {
                    continue;
                }
                $tag = trim((string) ($inb['tag'] ?? 'inbound-'.($inb['id'] ?? $count)));
                $protocol = strtolower(trim((string) ($inb['protocol'] ?? 'vless')));
                $port = (int) ($inb['port'] ?? 443);
                $this->line("  inbound {$tag} ({$protocol}:{$port})");
                if ($dryRun) {
                    $count++;
                    continue;
                }
                DB::table('svp_xray_inbounds')->updateOrInsert(
                    ['node_id' => $nodeId, 'tag' => $tag],
                    [
                        'remark' => (string) ($inb['remark'] ?? $tag),
                        'protocol' => $protocol,
                        'port' => $port,
                        'settings_json' => is_string($inb['settings'] ?? null) ? $inb['settings'] : json_encode($inb['settings'] ?? []),
                        'stream_settings_json' => is_string($inb['streamSettings'] ?? null) ? $inb['streamSettings'] : json_encode($inb['streamSettings'] ?? []),
                        'sniffing_json' => is_string($inb['sniffing'] ?? null) ? $inb['sniffing'] : json_encode($inb['sniffing'] ?? []),
                        'active' => 1,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
                $count++;
            }

            return $count;
        });

        if ($imported < 0) {
            return self::FAILURE;
        }

        $this->info(($dryRun ? 'Would import ' : 'Imported ').$imported.' inbound(s). Set plan panel_driver=native and xray_inbound_ref to cut over.');

        return self::SUCCESS;
    }
}
