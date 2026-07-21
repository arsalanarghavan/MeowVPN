<?php

namespace Tests\Feature\Bot;

use App\Modules\Core\Bot\Jobs\BotPollJob;
use App\Modules\Core\Bot\Jobs\ProcessInboundUpdateJob;
use App\Modules\Core\Bot\Services\BotPollingService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class BotPollingServiceTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_default_mode_is_webhook_so_poll_is_skipped(): void
    {
        $settings = app(SettingsStore::class);
        $settings->set('bot_enabled', true);
        $settings->set('telegram_enabled', true);
        $settings->set('telegram_bot_token', '1:TOK');
        $settings->set('telegram_update_mode', 'webhook');

        $this->assertFalse(app(BotPollingService::class)->platformShouldPoll('telegram'));
        $this->assertFalse(app(BotPollingService::class)->anyPlatformNeedsPoll());
    }

    public function test_platform_should_poll_requires_bot_enabled_and_token(): void
    {
        $settings = app(SettingsStore::class);
        $settings->set('bot_enabled', true);
        $settings->set('telegram_enabled', true);
        $settings->set('telegram_bot_token', '1:TOK');
        $settings->set('telegram_update_mode', 'polling');

        $this->assertTrue(app(BotPollingService::class)->platformShouldPoll('telegram'));

        $settings->set('bot_enabled', false);
        $this->assertFalse(app(BotPollingService::class)->platformShouldPoll('telegram'));
    }

    public function test_poll_platform_dispatches_updates_and_advances_offset(): void
    {
        Queue::fake();
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    ['update_id' => 41, 'message' => ['text' => 'hi']],
                    ['update_id' => 42, 'message' => ['text' => 'yo']],
                ],
            ], 200),
        ]);

        $settings = app(SettingsStore::class);
        $settings->merge([
            'bot_enabled' => true,
            'telegram_enabled' => true,
            'telegram_bot_token' => '1:TOK',
            'telegram_update_mode' => 'polling',
            'simplevpbot_tg_update_offset' => 40,
        ]);

        app(BotPollingService::class)->pollPlatform('telegram');

        Queue::assertPushed(ProcessInboundUpdateJob::class, 2);
        $this->assertSame(43, (int) $settings->get('simplevpbot_tg_update_offset', 0));
        $this->assertGreaterThan(0, (int) $settings->get('simplevpbot_tg_last_poll_at', 0));
    }

    public function test_bot_poll_job_noops_when_no_platform_needs_poll(): void
    {
        Queue::fake();
        app(SettingsStore::class)->set('telegram_update_mode', 'webhook');
        app(SettingsStore::class)->set('bale_update_mode', 'webhook');

        (new BotPollJob)->handle(app(BotPollingService::class));

        Queue::assertNothingPushed();
    }
}
