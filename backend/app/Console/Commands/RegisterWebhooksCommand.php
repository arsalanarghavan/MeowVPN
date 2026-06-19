<?php

namespace App\Console\Commands;

use App\Modules\Bale\Mutations\BaleMutations;
use App\Modules\Reseller\Services\ResellerWebhookService;
use App\Modules\Telegram\Mutations\BotMutations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RegisterWebhooksCommand extends Command
{
    protected $signature = 'svp:register-webhooks
        {--platform=both : telegram, bale, or both}
        {--reseller-id=0 : Register only this reseller (0 = all)}
        {--dry-run : Show what would be registered}';

    protected $description = 'Register Telegram/Bale webhooks for main bot and resellers';

    public function handle(
        BotMutations $telegram,
        BaleMutations $bale,
        ResellerWebhookService $resellerWebhooks,
    ): int {
        $platform = (string) $this->option('platform');
        $resellerId = (int) $this->option('reseller-id');
        $dryRun = (bool) $this->option('dry-run');
        $platforms = match ($platform) {
            'telegram' => ['telegram'],
            'bale' => ['bale'],
            default => ['telegram', 'bale'],
        };

        if ($dryRun) {
            $this->warn('Dry run — no webhooks will be registered.');
        }

        foreach ($platforms as $plat) {
            if ($dryRun) {
                $this->line("Would register main {$plat} webhook");
            } elseif ($plat === 'telegram') {
                $res = $telegram->botSetWebhook([], null);
                $this->report('main telegram', $res);
            } else {
                $res = $bale->botSetWebhook([], null);
                $this->report('main bale', $res);
            }
        }

        if (! Schema::hasTable('svp_users')) {
            return self::SUCCESS;
        }

        $q = DB::table('svp_users')->where('role', 'reseller');
        if ($resellerId > 0) {
            $q->where('id', $resellerId);
        }
        $resellers = $q->pluck('id');

        foreach ($resellers as $rid) {
            foreach ($platforms as $plat) {
                if ($dryRun) {
                    $this->line("Would register reseller {$rid} {$plat} webhook");
                    continue;
                }
                $res = $resellerWebhooks->setWebhook((int) $rid, $plat);
                $this->report("reseller {$rid} {$plat}", $res);
            }
        }

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>  $res */
    protected function report(string $label, array $res): void
    {
        if (! empty($res['ok'])) {
            $url = (string) ($res['url'] ?? $res['data']['url'] ?? '');
            $this->info("{$label}: ok".($url !== '' ? " → {$url}" : ''));

            return;
        }
        $this->warn("{$label}: ".(string) ($res['message'] ?? 'failed'));
    }
}
