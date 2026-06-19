<?php

namespace Tests\Feature\Xui;

use App\Modules\XuiPanel\Services\PanelEconomicsRenewalService;
use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class PanelEconomicsRenewalOffsetsTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Cache::flush();
    }

    public function test_uses_panel_cost_reminder_days_from_settings(): void
    {
        app(SettingsStore::class)->set('panel_cost_reminder_days', [3, 0]);
        app(SettingsStore::class)->set('notify_panel_cost_expiry', true);

        \Illuminate\Support\Facades\DB::table('svp_panels')->insert([
            'id' => 9,
            'label' => 'Econ',
            'panel_url' => 'https://econ.test',
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('svp_panel_economics_lines')->insert([
            'panel_id' => 9,
            'label' => 'line',
            'amount' => 10,
            'active' => 1,
            'expires_at' => now()->addDays(3)->toDateString(),
            'created_at' => now(),
        ]);

        $lineId = (int) \Illuminate\Support\Facades\DB::table('svp_panel_economics_lines')->max('id');
        app(PanelEconomicsRenewalService::class)->run();
        $this->assertTrue(Cache::has('svp_panel_econ_exp_'.$lineId.'_3'));
    }
}
