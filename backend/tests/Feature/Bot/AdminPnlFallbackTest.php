<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminHandlerRegistry;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminPnlFallbackTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
        app(SettingsStore::class)->set('telegram_admin_ids', [9001]);
    }

    public function test_unknown_pnl_callback_logs_warning(): void
    {
        Log::spy();
        $admin = SvpUser::query()->create([
            'tg_user_id' => 9001,
            'status' => 'approved',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(AdminHandlerRegistry::class)->handlePnl($ctx, ['pnl', 'unknown', 'xyz'], $admin, 9001, 0, 9001);
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('bot.admin.pnl.unknown_callback', \Mockery::type('array'));
        Http::assertSentCount(1);
    }
}
