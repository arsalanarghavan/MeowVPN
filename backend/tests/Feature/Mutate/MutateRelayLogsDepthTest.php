<?php

namespace Tests\Feature\Mutate;

use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateRelayLogsDepthTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();

        $settings = app(SettingsStore::class);
        $settings->set('telegram_relay_enabled', true);
        $settings->set('telegram_relay_admin_url', 'https://relay.test');
        $settings->set('telegram_relay_shared_secret', 'relay-secret');
    }

    public function test_logs_clear_truncates_svp_logs(): void
    {
        DB::table('svp_logs')->insert([
            'level' => 'info',
            'message' => 'to clear',
            'created_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'logs_clear',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame(0, DB::table('svp_logs')->count());
    }

    public function test_configs_assign_plan_happy_path(): void
    {
        $svcId = (int) DB::table('svp_services')->min('id');
        $planId = (int) DB::table('svp_plans')->min('id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'configs_assign_plan',
            'service_id' => $svcId,
            'plan_id' => $planId,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame($planId, (int) DB::table('svp_services')->where('id', $svcId)->value('plan_id'));
    }

    public function test_telegram_relay_test_sync_set_webhook(): void
    {
        Http::fake([
            'relay.test/internal/health' => Http::response(['ok' => true], 200),
            'relay.test/internal/config' => Http::response(['ok' => true], 200),
            'relay.test/internal/set-webhook' => Http::response(['ok' => true], 200),
        ]);

        foreach (['telegram_relay_test', 'telegram_relay_sync', 'telegram_relay_set_webhook'] as $op) {
            $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
                'op' => $op,
            ])->assertOk()->assertJsonPath('ok', true);
        }
    }
}
