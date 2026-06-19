<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminSettingsHandler;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminSettingsBotTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
        app(SettingsStore::class)->set('telegram_admin_ids', [9001]);
    }

    public function test_bots_tab_opens(): void
    {
        $admin = SvpUser::query()->create([
            'tg_user_id' => 9200,
            'status' => 'approved',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(AdminSettingsHandler::class)->openTab($ctx, 9200, $admin, 'bots');
        Http::assertSentCount(1);
    }
}
