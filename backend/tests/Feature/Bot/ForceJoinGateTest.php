<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\ForceJoinGate;
use App\Modules\Core\Bot\Services\RequiredChannelService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class ForceJoinGateTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        app(SettingsStore::class)->set('telegram_bot_token', '1:test-token');
        Cache::flush();
    }

    public function test_gate_skips_when_disabled(): void
    {
        app(SettingsStore::class)->set('force_join_telegram_enabled', false);
        $user = SvpUser::query()->create([
            'username' => 'fjuser',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');

        $this->assertFalse(app(ForceJoinGate::class)->shouldBlock($ctx, 1, 1, $user));
    }

    public function test_gate_allows_start_command_when_enabled(): void
    {
        app(SettingsStore::class)->merge([
            'force_join_telegram_enabled' => true,
            'force_join_telegram_chat_id' => -100123,
            'force_join_telegram_invite_link' => 'https://t.me/example',
        ]);
        $user = SvpUser::query()->create([
            'username' => 'fjuser2',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');

        $this->assertFalse(app(ForceJoinGate::class)->shouldBlock($ctx, 1, 1, $user, 'start'));
    }

    public function test_gate_fail_opens_on_cache_miss_and_schedules_refresh(): void
    {
        Http::fake([
            '*' => Http::response([
                'ok' => true,
                'result' => ['status' => 'left'],
            ], 200),
        ]);
        app(SettingsStore::class)->merge([
            'force_join_telegram_enabled' => true,
            'force_join_telegram_chat_id' => -100123,
            'force_join_telegram_invite_link' => 'https://t.me/example',
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 50,
            'username' => 'fjuser3',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');

        // Cache miss → interactive fail-open (do not block), background refresh scheduled.
        $this->assertFalse(app(ForceJoinGate::class)->shouldBlock($ctx, 50, 50, $user));
    }

    public function test_gate_blocks_when_negative_cache_hit(): void
    {
        app(SettingsStore::class)->merge([
            'force_join_telegram_enabled' => true,
            'force_join_telegram_chat_id' => -100123,
            'force_join_telegram_invite_link' => 'https://t.me/example',
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 50,
            'username' => 'fjneg',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        $key = app(RequiredChannelService::class)->membershipCacheKey('telegram', -100123, 50);
        Cache::put($key, '0', 45);
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200)]);

        $this->assertTrue(app(ForceJoinGate::class)->shouldBlock($ctx, 50, 50, $user));
    }

    public function test_user_passes_honors_force_refresh_and_caches(): void
    {
        Http::fake([
            '*/getChatMember' => Http::sequence()
                ->push(['ok' => true, 'result' => ['status' => 'member']], 200)
                ->push(['ok' => true, 'result' => ['status' => 'left']], 200),
        ]);
        app(SettingsStore::class)->merge([
            'force_join_telegram_enabled' => true,
            'force_join_telegram_chat_id' => -100123,
            'force_join_telegram_invite_link' => 'https://t.me/example',
            'force_join_cache_ttl_sec' => 120,
            'force_join_negative_cache_ttl_sec' => 30,
        ]);
        $ctx = new BotContext('telegram');
        $svc = app(RequiredChannelService::class);

        $this->assertTrue($svc->userPasses($ctx, 50));
        $key = $svc->membershipCacheKey('telegram', -100123, 50);
        $this->assertSame('1', Cache::get($key));

        // Cached positive — no second API call needed.
        $this->assertTrue($svc->userPasses($ctx, 50));

        // forceRefresh bypasses cache and updates to negative.
        $this->assertFalse($svc->userPasses($ctx, 50, true));
        $this->assertSame('0', Cache::get($key));
    }

    public function test_gate_works_for_bale_platform_keys(): void
    {
        app(SettingsStore::class)->merge([
            'bale_token' => '1:bale-token',
            'force_join_bale_enabled' => true,
            'force_join_bale_chat_id' => -100555,
            'force_join_bale_username' => 'balechan',
        ]);
        $user = SvpUser::query()->create([
            'bale_user_id' => 77,
            'username' => 'fjbale',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('bale');
        $key = app(RequiredChannelService::class)->membershipCacheKey('bale', -100555, 77);
        Cache::put($key, '0', 45);
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200)]);

        $this->assertTrue(app(ForceJoinGate::class)->shouldBlock($ctx, 77, 77, $user));
    }

    public function test_chjoin_verify_success_sends_welcome_keyboard(): void
    {
        Http::fake([
            '*/getChatMember' => Http::response([
                'ok' => true,
                'result' => ['status' => 'member'],
            ], 200),
            '*/answerCallbackQuery' => Http::response(['ok' => true], 200),
            '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);
        app(SettingsStore::class)->merge([
            'force_join_telegram_enabled' => true,
            'force_join_telegram_chat_id' => -100123,
            'force_join_telegram_invite_link' => 'https://t.me/example',
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 50,
            'username' => 'fjverify',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(ForceJoinGate::class)->handleVerifyCallback($ctx, 50, 50, 'cb1', $user);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'answerCallbackQuery'));

        $sendMessages = [];
        Http::assertSent(function ($request) use (&$sendMessages) {
            if (str_contains($request->url(), 'sendMessage')) {
                $sendMessages[] = $request->data();
            }

            return true;
        });
        $this->assertGreaterThanOrEqual(2, count($sendMessages));
        $withKeyboard = collect($sendMessages)->first(function (array $data) {
            $markup = $data['reply_markup'] ?? null;
            if (is_string($markup)) {
                $markup = json_decode($markup, true);
            }

            return is_array($markup) && isset($markup['keyboard']);
        });
        $this->assertNotNull($withKeyboard);
    }
}
