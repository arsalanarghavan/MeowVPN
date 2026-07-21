<?php

namespace Tests\Feature\Mutate;

use App\Services\SettingsStore;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §14 D.2.1 — force_join_publish sends to channel and pins (v16 + WP parity). */
class ForceJoinPublishChannelTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        app(SettingsStore::class)->merge([
            'telegram_bot_token' => '1:test-token',
            'force_join_telegram_enabled' => true,
            'force_join_telegram_chat_id' => -100999,
            'force_join_telegram_announce_text' => 'Join our channel',
        ]);
    }

    public function test_force_join_publish_sends_and_pins(): void
    {
        Http::fake([
            '*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 42],
            ], 200),
            '*/pinChatMessage' => Http::response(['ok' => true], 200),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'force_join_publish',
            'platform' => 'telegram',
        ])->assertOk()->assertJsonPath('ok', true)->assertJsonPath('message_id', 42);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && (int) ($request['chat_id'] ?? 0) === -100999
                && str_contains((string) ($request['text'] ?? ''), 'Join');
        });
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'pinChatMessage')
                && (int) ($request['message_id'] ?? 0) === 42;
        });
    }

    public function test_force_join_publish_legacy_text_payload(): void
    {
        Http::fake([
            '*' => Http::response(['ok' => true, 'result' => ['message_id' => 7]], 200),
        ]);
        app(SettingsStore::class)->set('force_join_channel_id', '-100999');
        app(SettingsStore::class)->set('force_join_prompt', 'Join our channel');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'force_join_publish',
            'text' => 'Join our channel',
        ])->assertOk()->assertJsonPath('ok', true);
    }
}
