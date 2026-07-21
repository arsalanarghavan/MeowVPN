<?php

namespace Tests\Feature\Mutate;

use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateP1MarketingUiRepairTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_marketing_lifecycle_confirm_defaults(): void
    {
        if (Schema::hasTable('svp_marketing_rules')) {
            DB::table('svp_marketing_rules')->insert([
                'owner_svp_user_id' => 0,
                'segment_key' => 'churned',
                'enabled' => false,
                'priority' => 10,
                'message_body' => 'hi {name} {code}',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAsAdmin();
        $res = $this->postJson('/api/v1/admin/mutate', [
            'op' => 'marketing_lifecycle_confirm_defaults',
        ])->assertOk()->json();

        $this->assertTrue((bool) ($res['ok'] ?? false));
        $this->assertTrue((bool) ($res['lifecycle_confirmed'] ?? $res['data']['lifecycle_confirmed'] ?? false));
        $this->assertTrue((bool) app(SettingsStore::class)->get('marketing_lifecycle_confirmed', false));
    }

    public function test_marketing_rule_save_and_delete_accept_rule_id(): void
    {
        if (! Schema::hasTable('svp_marketing_rules')) {
            $this->markTestSkipped('svp_marketing_rules unavailable');
        }

        $ruleId = (int) DB::table('svp_marketing_rules')->insertGetId([
            'owner_svp_user_id' => 0,
            'segment_key' => 'never_purchased',
            'enabled' => true,
            'priority' => 10,
            'message_body' => 'before {name}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertGreaterThan(0, $ruleId);

        $this->actingAsAdmin();
        $save = $this->postJson('/api/v1/admin/mutate', [
            'op' => 'marketing_rule_save',
            'rule_id' => $ruleId,
            'segment_key' => 'never_purchased',
            'enabled' => true,
            'message_body' => 'after {name} {code}',
        ])->assertOk()->json();

        $this->assertTrue((bool) ($save['ok'] ?? false));
        $this->assertSame($ruleId, (int) ($save['id'] ?? $save['data']['id'] ?? 0));
        $this->assertSame(
            'after {name} {code}',
            (string) DB::table('svp_marketing_rules')->where('id', $ruleId)->value('message_body')
        );

        $del = $this->postJson('/api/v1/admin/mutate', [
            'op' => 'marketing_rule_delete',
            'rule_id' => $ruleId,
        ])->assertOk()->json();

        $this->assertTrue((bool) ($del['ok'] ?? false));
        $this->assertNull(DB::table('svp_marketing_rules')->where('id', $ruleId)->first());
    }

    public function test_marketing_preview_message(): void
    {
        $ruleId = 0;
        if (Schema::hasTable('svp_marketing_rules')) {
            $ruleId = (int) DB::table('svp_marketing_rules')->insertGetId([
                'owner_svp_user_id' => 0,
                'segment_key' => 'never_purchased',
                'enabled' => true,
                'priority' => 10,
                'message_body' => 'سلام {name}! کد: {code}',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->assertGreaterThan(0, $ruleId);

        $this->actingAsAdmin();
        $res = $this->postJson('/api/v1/admin/mutate', [
            'op' => 'marketing_preview_message',
            'rule_id' => $ruleId,
        ])->assertOk()->json();

        $this->assertTrue((bool) ($res['ok'] ?? false));
        $msg = (string) ($res['message'] ?? $res['data']['message'] ?? '');
        $this->assertStringContainsString('PREVIEW-CODE', $msg);
    }

    public function test_bot_ui_group_create_and_delete(): void
    {
        app(SettingsStore::class)->set('bot_ui_layout', [
            'surfaces' => [
                'user_main' => [[
                    ['id' => 'user.main.buy', 'enabled' => true, 'glass' => false],
                    ['id' => 'user.main.wallet', 'enabled' => true, 'glass' => false],
                ]],
            ],
        ]);

        $this->actingAsAdmin();
        $create = $this->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_ui_group_create',
            'parent_surface' => 'user_main',
            'label_fa' => 'گروه تست',
            'label_en' => 'Test group',
            'member_actions' => ['user.main.buy'],
        ])->assertOk()->json();

        $this->assertTrue((bool) ($create['ok'] ?? false));
        $group = $create['group'] ?? $create['data']['group'] ?? null;
        $this->assertIsArray($group);
        $gid = (string) ($group['id'] ?? '');
        $this->assertNotSame('', $gid);

        $del = $this->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_ui_group_delete',
            'group_id' => $gid,
            'restore_to_parent' => true,
        ])->assertOk()->json();

        $this->assertTrue((bool) ($del['ok'] ?? false));
    }

    public function test_panels_repair_identities(): void
    {
        if (Schema::hasTable('svp_services')) {
            DB::table('svp_services')->delete();
        }

        $this->actingAsAdmin();
        $res = $this->postJson('/api/v1/admin/mutate', [
            'op' => 'panels_repair_identities',
            'panel_id' => 1,
            'limit' => 10,
        ])->assertOk()->json();

        $this->assertTrue((bool) ($res['ok'] ?? false));
        $this->assertArrayHasKey('scanned', $res + ($res['data'] ?? []));
        $this->assertArrayHasKey('repaired', $res + ($res['data'] ?? []));
    }

    public function test_panels_repair_identities_forbidden_for_reseller(): void
    {
        $this->actingAsReseller();
        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'panels_repair_identities',
            'panel_id' => 1,
        ])->assertForbidden();
    }

    public function test_plan_save_persists_multiple_inbound_ids(): void
    {
        if (! Schema::hasTable('svp_plans') || ! Schema::hasColumn('svp_plans', 'inbound_ids')) {
            $this->markTestSkipped('svp_plans.inbound_ids column unavailable');
        }

        $this->actingAsAdmin();
        $res = $this->postJson('/api/v1/admin/mutate', [
            'op' => 'plan',
            'plan_action' => 'add',
            'name' => 'Multi inbound plan',
            'category' => 'default',
            'plan_panel_id' => 1,
            'traffic_gb' => 30,
            'price' => 100000,
            'duration_days' => 30,
            'clients_count' => 1,
            'inbound_ids' => [1, 2, 3],
            'inbound_id' => 1,
            'plan_active' => 1,
        ])->assertOk()->json();

        $this->assertTrue((bool) ($res['ok'] ?? false));
        $planId = (int) ($res['plan_id'] ?? $res['data']['plan_id'] ?? 0);
        $this->assertGreaterThan(0, $planId);

        $row = DB::table('svp_plans')->where('id', $planId)->first();
        $this->assertNotNull($row);
        $decoded = json_decode((string) ($row->inbound_ids ?? ''), true);
        $this->assertSame([1, 2, 3], $decoded);
        $this->assertSame(1, (int) $row->inbound_id);
    }
}
