<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminTextsHandler;
use App\Modules\Core\Bot\Services\BotStateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminTextsBotTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    protected function admin(): SvpUser
    {
        return SvpUser::query()->create([
            'tg_user_id' => 9001,
            'status' => 'approved',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
    }

    public function test_texts_paging_callback(): void
    {
        for ($i = 0; $i < 12; $i++) {
            DB::table('svp_texts')->insert([
                'key_name' => 'key_'.$i,
                'value' => 'v'.$i,
            ]);
        }
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminTextsHandler::class)->handleCallback($ctx, ['pnl', 'txt', 'p', '10'], $admin, 9001);
        $state = app(BotStateService::class)->get($admin->fresh());
        $data = app(BotStateService::class)->data($admin->fresh());
        $this->assertSame('admin_texts_browse', $state);
        $this->assertSame(10, (int) ($data['offset'] ?? -1));
    }

    public function test_texts_next_reply_advances_offset(): void
    {
        for ($i = 0; $i < 5; $i++) {
            DB::table('svp_texts')->insert(['key_name' => 'k'.$i, 'value' => 'x']);
        }
        $admin = $this->admin();
        app(BotStateService::class)->set($admin, 'admin_texts_browse', ['offset' => 0]);
        $ctx = new BotContext('telegram');
        $handled = app(AdminTextsHandler::class)->routeText($ctx, $admin->fresh(), 9001, 'Next ▶');
        $this->assertTrue($handled);
        $data = app(BotStateService::class)->data($admin->fresh());
        $this->assertSame(AdminTextsHandler::PAGE_SIZE, (int) ($data['offset'] ?? 0));
    }
}
