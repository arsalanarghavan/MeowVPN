<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Bot\Services\TextService;
use App\Modules\Relay\Services\TelegramRelayService;
use App\Modules\XuiPanel\Services\XuiClient;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AdminAlertsService
{
    protected const PANEL_ALERT_PREFIX = 'svp_admin_panel_alert_';

    protected const QUEUE_ALERT_KEY = 'svp_admin_alert_queue_backlog';

    protected const BACKUP_ALERT_KEY = 'svp_admin_alert_backup_failed';

    protected const RELAY_FAIL_COUNTER = 'svp_admin_alert_relay_fail_count';

    protected const RELAY_ALERT_KEY = 'svp_admin_alert_relay_down';

    public function __construct(
        protected SettingsStore $settings,
        protected XuiClient $xui,
        protected AdminNotifyService $notify,
        protected TextService $texts,
    ) {}

    public function run(): void
    {
        if (! $this->settings->get('enabled', true)) {
            return;
        }

        $this->checkPanelDown();
        $this->checkWebhookQueueBacklog();
        $this->checkBackupFailed();
        $this->checkRelayUnreachable();
    }

    protected function checkPanelDown(): void
    {
        if (! $this->settings->get('notify_admin_panel_down', true)) {
            return;
        }

        $coolMin = max(5, (int) $this->settings->get('notify_admin_panel_down_cooldown', 30));

        if (! Schema::hasTable('svp_panels')) {
            return;
        }

        $panels = DB::table('svp_panels')->where('active', 1)->orderBy('sort_order')->get();
        if ($panels->isEmpty() && DB::table('svp_panels')->count() > 0) {
            $panels = DB::table('svp_panels')->orderBy('sort_order')->get();
        }

        if ($panels->isNotEmpty()) {
            foreach ($panels as $pn) {
                $pid = (int) ($pn->id ?? 0);
                if ($pid < 1) {
                    continue;
                }
                $label = trim((string) ($pn->label ?? ''));
                if ($label === '') {
                    $label = '#'.$pid;
                }
                $detail = '';
                $ok = $this->xui->runWithPanel($pid, function (XuiClient $client) use (&$detail) {
                    $detail = implode("\n", $client->probeAlertDetailLines());

                    return $client->loginWithRetries(6, 300000);
                });
                if (! $ok) {
                    Log::channel('svp-panel')->warning('panel.probe_failed', [
                        'panel_id' => $pid,
                        'label' => $label,
                    ]);
                    if ($this->panelDownSustained('p'.$pid)) {
                        $this->maybeNotifyPanelDown('p'.$pid, $coolMin, $label, $pid, $detail);
                    }
                } else {
                    $this->clearPanelDownSince('p'.$pid);
                }
            }

            return;
        }

        if (DB::table('svp_panels')->count() > 0) {
            return;
        }

        $panelUrl = trim((string) $this->settings->get('panel_url', ''));
        if ($panelUrl === '') {
            return;
        }

        $legacyPanel = [
            'panel_url' => $panelUrl,
            'panel_username' => (string) $this->settings->get('panel_username', ''),
            'panel_password' => (string) $this->settings->get('panel_password', ''),
            'panel_api_base' => (string) $this->settings->get('panel_api_base', 'panel/api'),
            'panel_api_token' => (string) $this->settings->get('panel_api_token', ''),
        ];
        $detail = '';
        $ok = $this->xui->runWithPanel(0, function (XuiClient $client) use (&$detail) {
            $detail = implode("\n", $client->probeAlertDetailLines());

            return $client->loginWithRetries(6, 300000);
        }, $legacyPanel);
        if (! $ok) {
            Log::channel('svp-panel')->warning('panel.probe_failed', ['panel_id' => 0, 'legacy' => true]);
            $legacyLabel = $this->texts->get('msg.cron.admin.panel_legacy_label', 'Legacy panel settings');
            if ($this->panelDownSustained('legacy')) {
                $this->maybeNotifyPanelDown('legacy', $coolMin, $legacyLabel, 0, $detail);
            }
        } else {
            $this->clearPanelDownSince('legacy');
        }
    }

    protected function panelDownSustained(string $suffix): bool
    {
        $sinceKey = self::PANEL_ALERT_PREFIX.'since:'.$suffix;
        $threshold = max(60, (int) config('svp.panel_down_alert_sustained_sec', 300));
        if (! Cache::has($sinceKey)) {
            Cache::put($sinceKey, time(), $threshold + 3600);

            return false;
        }

        $since = (int) Cache::get($sinceKey, time());

        return (time() - $since) >= $threshold;
    }

    protected function clearPanelDownSince(string $suffix): void
    {
        Cache::forget(self::PANEL_ALERT_PREFIX.'since:'.$suffix);
    }

    protected function maybeNotifyPanelDown(string $suffix, int $coolMin, string $label, int $panelId, string $detail = ''): void
    {
        $key = self::PANEL_ALERT_PREFIX.$suffix;
        if (Cache::has($key)) {
            return;
        }
        Cache::put($key, 1, $coolMin * 60);

        $msg = '🛠 '.$this->texts->get('msg.cron.admin.panel_login_failed', '3x-ui login from server failed.');
        $msg .= "\n\n📛 ".$this->texts->get('msg.cron.admin.panel_label', 'Panel:').' '.$label;
        if ($panelId > 0) {
            $msg .= "\n🆔 ".$this->texts->get('msg.cron.admin.panel_db_id', 'DB id:').' '.$panelId;
        }
        if (trim($detail) !== '') {
            $msg .= "\n\n".trim($detail);
        }
        $msg .= "\n\n".$this->texts->get('msg.cron.admin.panel_troubleshoot', 'Check panel URL, credentials, and firewall.');

        $this->notify->notifyAdmins($msg);
    }

    protected function checkWebhookQueueBacklog(): void
    {
        if (! Schema::hasTable('svp_inbound_queue')) {
            return;
        }

        $threshold = (int) config('svp.inbound_queue_alert_threshold', 1000);
        $pending = (int) DB::table('svp_inbound_queue')->where('status', 'pending')->count();
        if ($pending <= $threshold) {
            return;
        }

        if (Cache::has(self::QUEUE_ALERT_KEY)) {
            return;
        }
        Cache::put(self::QUEUE_ALERT_KEY, 1, 30 * 60);

        $msg = "⚠️ Webhook queue backlog: {$pending} pending rows (threshold {$threshold}).";
        Log::channel('svp-webhook')->warning('webhook.queue_backlog', ['pending' => $pending]);
        $this->notify->notifyAdmins($msg);
    }

    protected function checkBackupFailed(): void
    {
        $intervalMin = app(\App\Services\BackupIntervalResolver::class)->minutes();
        $maxAge = $intervalMin * 2 * 60;
        $lastBuilt = (int) $this->settings->get('backup_last_built_at', 0);
        if ($lastBuilt > 0 && (time() - $lastBuilt) <= $maxAge) {
            return;
        }

        if (Cache::has(self::BACKUP_ALERT_KEY)) {
            return;
        }
        Cache::put(self::BACKUP_ALERT_KEY, 1, 60 * 60);

        $ageHours = $lastBuilt > 0 ? (int) floor((time() - $lastBuilt) / 3600) : -1;
        $msg = $lastBuilt > 0
            ? "⚠️ Backup stale: last successful backup {$ageHours}h ago (expected every {$intervalMin} min)."
            : '⚠️ Backup never completed successfully on this Laravel instance.';

        Log::channel('svp')->warning('backup.stale', ['last_built_at' => $lastBuilt]);
        $this->notify->notifyAdmins($msg);
    }

    protected function checkRelayUnreachable(): void
    {
        if (! svp_modules()->isEnabled('relay')) {
            return;
        }

        $relay = app(TelegramRelayService::class);
        if (! $relay->isEnabled()) {
            Cache::forget(self::RELAY_FAIL_COUNTER);

            return;
        }

        $health = $relay->health();
        if (! empty($health['ok'])) {
            Cache::forget(self::RELAY_FAIL_COUNTER);

            return;
        }

        $fails = (int) Cache::increment(self::RELAY_FAIL_COUNTER);
        if ($fails === 1) {
            Cache::put(self::RELAY_FAIL_COUNTER, 1, 3600);
        }

        $threshold = (int) config('svp.relay_alert_fail_threshold', 3);
        if ($fails < $threshold) {
            return;
        }

        if (Cache::has(self::RELAY_ALERT_KEY)) {
            return;
        }
        Cache::put(self::RELAY_ALERT_KEY, 1, 30 * 60);

        $msg = '⚠️ Telegram relay unreachable after '.$fails.' consecutive health checks.';
        Log::channel('svp-relay')->warning('relay.unreachable', ['fails' => $fails]);
        $this->notify->notifyAdmins($msg);
    }
}
