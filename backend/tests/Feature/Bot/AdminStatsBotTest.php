<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminStatsHandler;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminStatsBotTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_monitoring_dashboard_sends_message(): void
    {
        $admin = SvpUser::query()->create([
            'tg_user_id' => 9400,
            'status' => 'approved',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(AdminStatsHandler::class)->openMonitoring($ctx, 9400, $admin);
        Http::assertSentCount(1);
    }
}
