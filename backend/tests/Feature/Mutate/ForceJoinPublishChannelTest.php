<?php

namespace Tests\Feature\Mutate;

use App\Modules\Core\Bot\Services\BotRuntime;
use App\Services\SettingsStore;
use Mockery;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §14 D.2.1 — force_join_publish sends to channel (v16). */
class ForceJoinPublishChannelTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        app(SettingsStore::class)->set('force_join_channel_id', '-100999');
        app(SettingsStore::class)->set('force_join_prompt', 'Join our channel');
    }

    public function test_force_join_publish_invokes_bot_runtime_send(): void
    {
        $runtime = Mockery::mock(BotRuntime::class);
        $runtime->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($ctx, $chatId, $text) {
                return (int) $chatId === -100999 && str_contains((string) $text, 'Join');
            });
        $this->app->instance(BotRuntime::class, $runtime);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'force_join_publish',
            'text' => 'Join our channel',
        ])->assertOk()->assertJsonPath('ok', true);
    }
}
