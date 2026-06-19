<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class ScheduleListTest extends TestCase
{
    use RefreshDatabase;
    use TogglesModules;

    public function test_schedule_list_includes_core_cron_jobs(): void
    {
        Artisan::call('schedule:list');
        $out = Artisan::output();
        $this->assertStringContainsString('svp:expiry', $out);
        $this->assertStringContainsString('svp:autorenew', $out);
        $this->assertStringContainsString('svp:admin_alerts', $out);
    }

    public function test_panel_economics_not_scheduled_when_xui_panel_disabled(): void
    {
        $this->setModuleEnabled('xui_panel', false);
        Artisan::call('schedule:list');
        $out = Artisan::output();
        $this->assertStringNotContainsString('svp:panel_economics_renewal', $out);
    }

    public function test_backup_not_scheduled_when_backup_module_disabled(): void
    {
        $this->setModuleEnabled('backup', false);
        Artisan::call('schedule:list');
        $out = Artisan::output();
        $this->assertStringNotContainsString('svp:backup', $out);
    }

    public function test_schedule_list_includes_all_fourteen_svp_jobs_when_modules_on(): void
    {
        Artisan::call('schedule:list');
        $out = Artisan::output();
        foreach ([
            'svp:backup', 'svp:expiry', 'svp:purge_expired', 'svp:autorenew', 'svp:broadcast',
            'svp:users_bulk', 'svp:panel_online', 'svp:panel_service_sync', 'svp:inbound_clients_cache',
            'svp:idle_offers', 'svp:marketing', 'svp:admin_alerts', 'svp:panel_economics_renewal',
            'svp:inbound_queue_drain',
        ] as $name) {
            $this->assertStringContainsString($name, $out, "Missing scheduled job: {$name}");
        }
    }

    public function test_purge_expired_not_scheduled_when_xui_panel_disabled(): void
    {
        $this->setModuleEnabled('xui_panel', false);
        Artisan::call('schedule:list');
        $this->assertStringNotContainsString('svp:purge_expired', Artisan::output());
    }
}
