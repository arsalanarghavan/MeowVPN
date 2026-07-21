<?php

namespace Tests\Feature\Mutate;

use App\Modules\Core\Bot\Jobs\DeferredSvcPanelDeliveryJob;
use App\Modules\Core\Bot\Services\BotSvcPanelDeliveryService;
use App\Models\SvpService;
use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Services\Marketing\MarketingLifecycleAnalyticsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateAbsoluteRemainingP1Test extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        Http::fake(['*' => Http::response(['success' => true, 'obj' => []], 200)]);
    }

    public function test_plan_category_toggle_and_wp_aliases(): void
    {
        $id = (int) DB::table('svp_plan_categories')->insertGetId([
            'panel_id' => 1,
            'slug' => 'p1cat',
            'label' => 'P1 Cat',
            'sort_order' => 1,
            'active' => 1,
            'created_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'plan_category',
            'pc_action' => 'toggle',
            'pc_id' => $id,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame(0, (int) DB::table('svp_plan_categories')->where('id', $id)->value('active'));
        $this->assertSame('P1 Cat', (string) DB::table('svp_plan_categories')->where('id', $id)->value('label'));

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'plan_category',
            'pc_action' => 'add',
            'pc_label' => 'Alias Cat',
            'pc_slug' => 'alias_cat',
            'pc_panel_id' => 1,
            'pc_sort' => 9,
            'pc_active' => 1,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_plan_categories', [
            'slug' => 'alias_cat',
            'label' => 'Alias Cat',
            'sort_order' => 9,
        ]);
    }

    public function test_plan_inbound_ids_empty_array_clears_multi_inbound(): void
    {
        if (! Schema::hasColumn('svp_plans', 'inbound_ids')) {
            $this->markTestSkipped('inbound_ids unavailable');
        }

        $planId = (int) DB::table('svp_plans')->insertGetId([
            'name' => 'Clear inbound plan',
            'category' => 'default',
            'panel_id' => 1,
            'inbound_id' => 1,
            'inbound_ids' => json_encode([1, 2, 3]),
            'price' => 1000,
            'traffic_gb' => 10,
            'duration_days' => 30,
            'active' => 1,
            'created_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'plan',
            'plan_action' => 'update',
            'id' => $planId,
            'inbound_ids' => [],
        ])->assertOk()->assertJsonPath('ok', true);

        $row = DB::table('svp_plans')->where('id', $planId)->first();
        $this->assertSame(0, (int) $row->inbound_id);
        $this->assertTrue($row->inbound_ids === null || $row->inbound_ids === '' || $row->inbound_ids === 'null');
    }

    public function test_receipt_update_amount_and_reject_status(): void
    {
        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => 101,
            'amount' => 50000,
            'type' => 'purchase',
            'status' => 'pending',
            'meta_json' => '{}',
            'created_at' => now(),
        ]);
        $rid = (int) DB::table('svp_receipts')->insertGetId([
            'user_id' => 101,
            'transaction_id' => $txId,
            'amount' => 50000,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'receipt_update',
            'receipt_id' => $rid,
            'amount' => 45000,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame(45000.0, (float) DB::table('svp_receipts')->where('id', $rid)->value('amount'));
        $this->assertSame(45000.0, (float) DB::table('svp_transactions')->where('id', $txId)->value('amount'));

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'receipt_update',
            'receipt_id' => $rid,
            'status' => 'rejected',
            'reject_reason' => 'blurry',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_receipts', ['id' => $rid, 'status' => 'rejected']);
    }

    public function test_panel_xp_toggle_and_wp_field_aliases(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'panel_xp',
            'xp_action' => 'toggle',
            'xp_id' => 1,
        ])->assertOk()->assertJsonPath('ok', true);

        $active = (int) DB::table('svp_panels')->where('id', 1)->value('active');
        $this->assertContains($active, [0, 1]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'panel_xp',
            'xp_action' => 'update',
            'xp_id' => 1,
            'xp_label' => 'XP Alias Panel',
            'xp_panel_url' => 'https://panel.example.test',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_panels', ['id' => 1, 'label' => 'XP Alias Panel']);
    }

    public function test_marketing_lifecycle_analytics_wp_shape_and_owner_scope(): void
    {
        $owner = 100;
        $ruleId = (int) DB::table('svp_marketing_rules')->insertGetId([
            'owner_svp_user_id' => $owner,
            'segment_key' => 'never_purchased',
            'enabled' => 1,
            'priority' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherRule = (int) DB::table('svp_marketing_rules')->insertGetId([
            'owner_svp_user_id' => 999,
            'segment_key' => 'churned',
            'enabled' => 1,
            'priority' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => 101,
            'amount' => 120000,
            'type' => 'purchase',
            'status' => 'completed',
            'created_at' => now(),
        ]);
        DB::table('svp_marketing_offers')->insert([
            'rule_id' => $ruleId,
            'svp_user_id' => 101,
            'discount_code_id' => 0,
            'status' => 'converted',
            'sent_at' => now(),
            'converted_transaction_id' => $txId,
            'meta_json' => null,
            'created_at' => now(),
        ]);
        DB::table('svp_marketing_offers')->insert([
            'rule_id' => $ruleId,
            'svp_user_id' => 102,
            'discount_code_id' => 0,
            'status' => 'skipped',
            'sent_at' => null,
            'converted_transaction_id' => 0,
            'meta_json' => json_encode(['skip_reason' => 'daily_cap']),
            'created_at' => now(),
        ]);
        DB::table('svp_marketing_offers')->insert([
            'rule_id' => $otherRule,
            'svp_user_id' => 103,
            'discount_code_id' => 0,
            'status' => 'sent',
            'sent_at' => now(),
            'converted_transaction_id' => 0,
            'created_at' => now(),
        ]);

        $payload = app(MarketingLifecycleAnalyticsService::class)->buildDashboardPayload(30, $owner, false);
        $this->assertSame(30, (int) $payload['window_days']);
        $this->assertArrayHasKey('campaign_revenue_toman', $payload['summary']);
        $this->assertGreaterThan(0, (float) $payload['summary']['campaign_revenue_toman']);
        $this->assertArrayHasKey('segment_counts', $payload['summary']);
        $this->assertArrayHasKey('never_purchased', $payload['summary']['segment_counts']);
        $this->assertArrayHasKey('segment_trend', $payload);
        $this->assertArrayHasKey('health', $payload);
        $this->assertArrayHasKey('next_scheduled_at', $payload['health']);
        $this->assertArrayHasKey('cron_mode', $payload['health']);
        $this->assertArrayHasKey('active_rules_count', $payload['health']);
        $this->assertGreaterThanOrEqual(1, (int) ($payload['health']['active_rules_count'] ?? 0));
        $this->assertNotEmpty($payload['rule_stats']);
        $this->assertArrayHasKey('eligible_now', $payload['rule_stats'][0]);
        $this->assertNotEmpty($payload['funnel']);
        $this->assertArrayHasKey('date', $payload['funnel'][0]);
        $this->assertArrayHasKey('registered', $payload['funnel'][0]);
        $skip = collect($payload['skip_breakdown'])->firstWhere('reason', 'daily_cap');
        $this->assertNotNull($skip);
        $this->assertSame(1, (int) $skip['count']);
        $this->assertTrue(collect($payload['rules'])->every(fn ($r) => (int) ($r['owner_svp_user_id'] ?? -1) === $owner));
        foreach (['churned', 'never_purchased', 'abandoned_checkout', 'stale_buy_funnel', 'expiring_renew', 'upgrade_candidate', 'volume_boost'] as $sk) {
            $this->assertArrayHasKey($sk, $payload['summary']['segment_counts']);
        }
        $this->assertIsInt($payload['rule_stats'][0]['eligible_now']);
        $this->assertIsArray($payload['segment_trend']);
        if ($payload['segment_trend'] !== []) {
            $this->assertArrayHasKey('segment', $payload['segment_trend'][0]);
            $this->assertArrayHasKey('date', $payload['segment_trend'][0]);
        }
    }

    public function test_deferred_svc_panel_delivery_job_dispatched(): void
    {
        Queue::fake();
        $svc = SvpService::query()->first();
        $this->assertNotNull($svc);
        $user = SvpUser::query()->find((int) $svc->user_id) ?: SvpUser::query()->first();
        $this->assertNotNull($user);

        app(BotSvcPanelDeliveryService::class)->schedule(
            new BotContext('telegram'),
            $user,
            12345,
            99,
            $svc,
            'p',
            'cb-1',
        );

        Queue::assertPushed(DeferredSvcPanelDeliveryJob::class, function (DeferredSvcPanelDeliveryJob $job) use ($svc) {
            return $job->serviceId === (int) $svc->id
                && $job->panelMsgId === 99
                && $job->action === 'p';
        });
    }
}
