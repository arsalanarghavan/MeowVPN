<?php

namespace App\Jobs;

use App\Models\Server;
use App\Services\VpnPanelFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sync active_users_count for all active servers (Marzban and Hiddify) with the central database.
 * Runs periodically so the central node has accurate user counts even if
 * users are created/deleted directly on the panel.
 */
class SyncAllServersUserCount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var string Run on central node; use queue:work --queue=sync,default on central. */
    public string $queue = 'sync';

    public int $tries = 1;

    public int $timeout = 300;

    public function handle(VpnPanelFactory $panelFactory): void
    {
        $servers = Server::where('is_active', true)->get();

        $synced = 0;
        $failed = 0;

        foreach ($servers as $server) {
            try {
                $panelService = $panelFactory->make($server);
                $health = $panelService->getServerHealth($server);

                $actualCount = (int) ($health['total_users'] ?? $health['active_users'] ?? 0);
                $server->update(['active_users_count' => $actualCount]);
                $synced++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning("SyncAllServersUserCount: failed for server {$server->id} ({$server->name}): " . $e->getMessage());
            }
        }

        if ($synced > 0 || $failed > 0) {
            Log::info("SyncAllServersUserCount: {$synced} servers synced, {$failed} failed.");
        }
    }
}
