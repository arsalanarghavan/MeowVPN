<?php

namespace Tests\Feature\Telegram;

use App\Models\SvpTelegramMirrorBot;
use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class TelegramMirrorMutateTest extends TestCase
{
    use CreatesSvpTestSchema;
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        app(SettingsStore::class)->set('bot_enabled', true);
        app(SettingsStore::class)->set('telegram_enabled', true);
    }

    public function test_admin_can_save_toggle_and_delete_mirror_bot(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['username' => 'mirror_bot']], 200),
        ]);

        $save = $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_mirror_save',
            'label' => 'Mirror A',
            'telegram_token' => '123:ABC',
        ])->assertOk()->assertJsonPath('ok', true);

        $mirrorId = (int) $save->json('mirror_id');
        $this->assertGreaterThan(0, $mirrorId);
        $this->assertDatabaseHas('svp_telegram_mirror_bots', [
            'id' => $mirrorId,
            'label' => 'Mirror A',
            'enabled' => 1,
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_mirror_toggle',
            'mirror_id' => $mirrorId,
            'enabled' => false,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_telegram_mirror_bots', [
            'id' => $mirrorId,
            'enabled' => 0,
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_mirror_delete',
            'mirror_id' => $mirrorId,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('svp_telegram_mirror_bots', ['id' => $mirrorId]);
    }

    public function test_mirror_set_webhook_uses_expected_path(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['username' => 'mirror_bot']], 200),
        ]);

        $mirrorId = (int) SvpTelegramMirrorBot::query()->insertGetId([
            'label' => 'Mirror WH',
            'telegram_token' => Crypt::encryptString('123:ABC'),
            'telegram_bot_username' => '',
            'webhook_secret' => Crypt::encryptString('mirror-wh-secret'),
            'telegram_secret_token' => '',
            'enabled' => true,
            'sort_order' => 0,
            'updated_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_mirror_set_webhook',
            'mirror_id' => $mirrorId,
        ])->assertOk()->assertJsonPath('ok', true);

        Http::assertSent(function ($request) use ($mirrorId) {
            $url = $request->url();
            if (! str_contains($url, 'setWebhook')) {
                return false;
            }
            $body = $request->data();
            $hookUrl = is_array($body) ? (string) ($body['url'] ?? '') : '';

            return str_contains($hookUrl, "/webhook/telegram/mirror/{$mirrorId}/");
        });
    }

    public function test_mirror_delete_webhook_test_and_diagnostics_via_http(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['username' => 'mirror_bot', 'url' => '']], 200),
        ]);

        $mirrorId = (int) SvpTelegramMirrorBot::query()->insertGetId([
            'label' => 'Mirror Ops',
            'telegram_token' => Crypt::encryptString('123:ABC'),
            'telegram_bot_username' => 'mirror_bot',
            'webhook_secret' => Crypt::encryptString('mirror-ops-secret'),
            'telegram_secret_token' => '',
            'enabled' => true,
            'sort_order' => 0,
            'updated_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_mirror_test',
            'mirror_id' => $mirrorId,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_mirror_diagnostics',
            'mirror_id' => $mirrorId,
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['local', 'issues']);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_mirror_delete_webhook',
            'mirror_id' => $mirrorId,
        ])->assertOk()->assertJsonPath('ok', true);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'deleteWebhook');
        });
    }

    public function test_reseller_blocked_from_mirror_ops(): void
    {
        $this->actingAsReseller()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_mirror_save',
            'label' => 'Nope',
        ])->assertForbidden();
    }

    public function test_bots_tab_includes_mirror_list(): void
    {
        SvpTelegramMirrorBot::query()->create([
            'label' => 'Listed Mirror',
            'telegram_token' => Crypt::encryptString('123:ABC'),
            'webhook_secret' => Crypt::encryptString('listed-secret'),
            'enabled' => true,
        ]);

        $json = $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=bots')->assertOk()->json();
        $this->assertIsArray($json['telegramMirrorsList'] ?? null);
        $labels = array_column($json['telegramMirrorsList'], 'label');
        $this->assertContains('Listed Mirror', $labels);
    }
}
