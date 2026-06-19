<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Services\AdminAlertsService;
use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminAlertsCooldownTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Cache::flush();
    }

    public function test_panel_down_alert_respects_cooldown(): void
    {
        $settings = app(SettingsStore::class);
        $settings->set('notify_admin_panel_down', true);
        $settings->set('notify_admin_panel_down_cooldown', 60);

        DB::table('svp_panels')->insert([
            'label' => 'Cool Panel',
            'panel_url' => 'https://down.test',
            'panel_username' => 'u',
            'panel_password' => 'p',
            'active' => 1,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $svc = app(AdminAlertsService::class);
        $svc->run();

        Cache::put('svp_admin_panel_alert_p1', now()->timestamp, 3600);
        $svc->run();
        $this->assertTrue(Cache::has('svp_admin_panel_alert_p1'));
    }
}
