<?php

namespace App\Modules\XuiPanel\Services;

use App\Modules\PasarGuard\Services\PanelClientFactory;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PanelSessionKeeperService
{
    public function __construct(
        protected PanelClientFactory $factory,
        protected SettingsStore $settings,
    ) {}

    /**
     * @return array{ok:bool, panels:int, warmed:int, updated_at:int}
     */
    public function run(): array
    {
        $panels = 0;
        $warmed = 0;
        if (! Schema::hasTable('svp_panels')) {
            return ['ok' => true, 'panels' => 0, 'warmed' => 0, 'updated_at' => time()];
        }

        $rows = DB::table('svp_panels')->where('active', 1)->orderBy('sort_order')->orderBy('id')->get();
        foreach ($rows as $panel) {
            $panelId = (int) $panel->id;
            if ($panelId < 1) {
                continue;
            }
            $provider = (string) ($panel->panel_provider ?? 'xui');
            if ($provider === 'pasarguard') {
                continue;
            }
            $token = trim((string) ($panel->panel_api_token ?? ''));
            $user = trim((string) ($panel->panel_username ?? ''));
            $pass = trim((string) ($panel->panel_password ?? ''));
            if ($token !== '' || $user === '' || $pass === '') {
                continue;
            }
            $panels++;
            try {
                $result = $this->factory->runWithPanel($panelId, function ($client) {
                    return $client->loginWithRetries(6, 300000);
                }, (array) $panel);
                if ($result) {
                    $warmed++;
                }
            } catch (\Throwable) {
                // Skip unreachable panels; session keeper is best-effort.
            }
        }

        $now = time();
        $this->settings->set('last_session_keeper_at', $now);

        return [
            'ok' => true,
            'panels' => $panels,
            'warmed' => $warmed,
            'updated_at' => $now,
        ];
    }
}
