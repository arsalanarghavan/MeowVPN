<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\StartHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class BaleRegistrationTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
        DB::table('svp_settings')->insert(['key_name' => 'bale_admin_ids', 'value' => json_encode([9999])]);
    }

    public function test_pending_bale_user_creates_approval_queue_row(): void
    {
        $user = SvpUser::query()->create([
            'bale_user_id' => 700,
            'status' => 'pending',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('bale');
        app(StartHandler::class)->handle($ctx, [
            'chat_id' => 700,
            'from' => ['id' => 700],
            'text' => '/start',
            'user' => $user,
        ]);
        $this->assertSame(1, (int) DB::table('svp_pending_approvals')->where('user_id', $user->id)->count());
    }
}
