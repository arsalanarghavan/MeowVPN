<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminLogsHandler;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminLogsBotTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_logs_callback_opens_tab(): void
    {
        $admin = SvpUser::query()->create([
            'tg_user_id' => 9500,
            'status' => 'approved',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(AdminLogsHandler::class)->handleCallback($ctx, ['pnl', 'lg', '0', 'audit'], $admin, 9500);
        Http::assertSentCount(1);
    }
}
