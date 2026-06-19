<?php

namespace Tests\Feature\Xui;

use App\Modules\XuiPanel\Jobs\PanelOnlineJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class PanelOnlineJobTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        DB::table('svp_panels')->insert([
            'id' => 1,
            'label' => 'Test',
            'panel_url' => 'https://panel.test',
            'panel_api_token' => 'tok',
            'panel_api_flavor' => 'legacy_inbound',
            'active' => 1,
            'created_at' => now(),
        ]);
    }

    public function test_panel_online_job_upserts_daily_max(): void
    {
        Http::fake([
            'https://panel.test/panel/api/inbounds/onlines' => Http::response([
                'success' => true,
                'obj' => ['a@x.com', 'b@x.com', 'c@x.com'],
            ]),
            'https://panel.test/panel/api/inbounds/list' => Http::response(['success' => true, 'obj' => []]),
            'https://panel.test/panel/api/*' => Http::response(['success' => true, 'obj' => []]),
        ]);

        (new PanelOnlineJob)->handle(app(\App\Modules\XuiPanel\Services\XuiClient::class));

        $row = DB::table('svp_panel_online_daily')
            ->where('panel_id', 1)
            ->where('stat_date', now()->toDateString())
            ->first();
        $this->assertNotNull($row);
        $this->assertSame(3, (int) $row->max_online);
    }
}
