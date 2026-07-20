<?php

namespace Tests\Unit\XuiPanel;

use App\Modules\XuiPanel\Services\PanelLimitIpBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class PanelLimitIpBackfillServiceTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_marks_done_when_no_rows_need_backfill(): void
    {
        $out = app(PanelLimitIpBackfillService::class)->runBatch();

        $this->assertTrue($out['done']);
        $this->assertSame('1', (string) DB::table('svp_settings')->where('key_name', PanelLimitIpBackfillService::DONE_KEY)->value('value'));
    }
}
