<?php

namespace Tests\Feature;

use App\Modules\Telegram\Services\TelegramMirrorBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class TelegramMirrorBotTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['id' => 1, 'username' => 'mirror_bot', 'is_bot' => true],
            ], 200),
        ]);
    }

    public function test_telegram_mirror_save_creates_row(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_mirror_save',
            'label' => 'Mirror A',
            'telegram_token' => '999:TOKEN',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_telegram_mirror_bots', [
            'label' => 'Mirror A',
            'telegram_bot_username' => 'mirror_bot',
            'enabled' => 1,
        ]);
    }

    public function test_telegram_mirror_toggle(): void
    {
        $this->actingAsAdmin();
        $id = app(TelegramMirrorBotService::class)->insert([
            'label' => 'Toggle me',
            'telegram_token' => '1:abc',
            'enabled' => true,
        ]);

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_mirror_toggle',
            'mirror_id' => $id,
            'enabled' => false,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_telegram_mirror_bots', [
            'id' => $id,
            'enabled' => 0,
        ]);
    }

    public function test_mirror_webhook_forbidden_bad_secret(): void
    {
        $id = app(TelegramMirrorBotService::class)->insert([
            'label' => 'W',
            'telegram_token' => '1:abc',
        ]);

        $this->postJson("/api/v1/webhook/telegram/mirror/{$id}/wrong-secret", [
            'update_id' => 1,
        ])->assertForbidden()->assertJsonPath('message', 'forbidden');
    }

    public function test_mirror_webhook_ok_enqueues_with_mirror_id(): void
    {
        $secret = 'mirror-wh-secret';
        $id = DB::table('svp_telegram_mirror_bots')->insertGetId([
            'label' => 'Live',
            'telegram_token' => Crypt::encryptString('1:abc'),
            'telegram_bot_username' => 'live',
            'webhook_secret' => Crypt::encryptString($secret),
            'telegram_secret_token' => '',
            'enabled' => 1,
            'sort_order' => 0,
            'updated_at' => now(),
        ]);

        $this->postJson("/api/v1/webhook/telegram/mirror/{$id}/{$secret}", [
            'update_id' => 77,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 42, 'first_name' => 'M'],
                'chat' => ['id' => 42, 'type' => 'private'],
                'text' => '/start',
            ],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('svp_inbound_queue', [
            'platform' => 'telegram',
            'mirror_bot_id' => $id,
            'reseller_svp_user_id' => 0,
        ]);
    }

    public function test_mirror_webhook_health_get(): void
    {
        $this->getJson('/api/v1/webhook/telegram/mirror/1/any')
            ->assertOk()
            ->assertJsonPath('alive', true)
            ->assertJsonPath('scope', 'mirror');
    }
}
