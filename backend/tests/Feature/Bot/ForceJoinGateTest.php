<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\ForceJoinGate;
use App\Services\SettingsStore;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class ForceJoinGateTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_gate_skips_when_disabled(): void
    {
        app(SettingsStore::class)->set('force_join_enabled', false);
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
            'force_join_enabled' => true,
            'force_join_channel_id' => '-100123',
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

    public function test_gate_blocks_non_member_via_get_chat_member(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response([
                'ok' => true,
                'result' => ['status' => 'left'],
            ], 200),
        ]);
        app(SettingsStore::class)->merge([
            'force_join_enabled' => true,
            'force_join_channel_id' => '-100123',
            'force_join_channel_url' => 'https://t.me/example',
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 50,
            'username' => 'fjuser3',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');

        $this->assertTrue(app(ForceJoinGate::class)->shouldBlock($ctx, 50, 50, $user));
    }
}
