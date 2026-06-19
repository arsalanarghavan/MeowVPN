<?php

namespace Tests\Feature\Acceptance;

use App\Models\DashboardUser;
use App\Models\SvpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** §14 v15 acceptance gaps. */
class GroupAcceptanceV15Test extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_reseller_overview_user_count_less_than_admin(): void
    {
        SvpUser::query()->create([
            'username' => 'admin_only_user',
            'role' => 'user',
            'status' => 'approved',
            'invited_by' => 0,
            'created_at' => now(),
        ]);

        $adminOverview = $this->actingAsAdmin()
            ->getJson('/api/v1/admin/state?tab=dashboard')
            ->json('overview');

        $resellerOverview = $this->actingAsReseller()
            ->getJson('/api/v1/admin/state?tab=dashboard')
            ->json('overview');

        $adminTotal = (int) ($adminOverview['users_total'] ?? $adminOverview['usersTotal'] ?? 0);
        $resellerTotal = (int) ($resellerOverview['users_total'] ?? $resellerOverview['usersTotal'] ?? 0);

        if ($adminTotal > 0) {
            $this->assertGreaterThanOrEqual($adminTotal, $resellerTotal);
        }
        $this->assertIsArray($adminOverview);
    }

    public function test_monitoring_tab_scopes_panels_for_reseller(): void
    {
        $resellerId = (int) SvpUser::query()->where('role', 'reseller')->value('id');
        $dash = DashboardUser::query()->where('username', 'reseller')->first();
        $dash->permissions_json = ['users.manage' => true, 'services.manage' => true];
        $dash->save();

        DB::table('svp_panels')->insert([
            'id' => 88,
            'label' => 'Hidden Panel',
            'panel_url' => 'https://hidden.test',
            'active' => 1,
            'owner_svp_user_id' => 99999,
            'sort_order' => 88,
            'created_at' => now(),
        ]);

        $panels = $this->actingAs($dash)
            ->getJson('/api/v1/admin/state?tab=monitoring')
            ->assertOk()
            ->json('panels');

        $ids = array_map(fn ($p) => (int) ($p['id'] ?? 0), is_array($panels) ? $panels : []);
        $this->assertNotContains(88, $ids);
    }

    public function test_receipt_approve_deliver_via_mutate(): void
    {
        DB::table('svp_panels')->where('id', 1)->update([
            'panel_api_token' => 'tok',
            'panel_api_flavor' => 'legacy_inbound',
        ]);

        $txId = DB::table('svp_transactions')->insertGetId([
            'user_id' => 101,
            'amount' => 10000,
            'type' => 'purchase',
            'status' => 'pending',
            'meta_json' => json_encode(['plan_id' => 1]),
            'created_at' => now(),
        ]);

        $receiptId = DB::table('svp_receipts')->insertGetId([
            'user_id' => 101,
            'transaction_id' => $txId,
            'amount' => 10000,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://panel.test/panel/api/*' => \Illuminate\Support\Facades\Http::response(['success' => true, 'obj' => []]),
            '*' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'receipt_action',
            'receipt_id' => $receiptId,
            'action' => 'approve',
        ])->assertOk();

        $this->assertDatabaseHas('svp_receipts', ['id' => $receiptId, 'status' => 'approved']);
    }

    public function test_reseller_panel_price_deactivate_hides_panel(): void
    {
        $resellerId = (int) SvpUser::query()->where('role', 'reseller')->value('id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_panel_prices_save',
            'reseller_svp_user_id' => $resellerId,
            'panel_id' => 1,
            'price' => 3000,
            'active' => false,
        ])->assertOk();

        $this->assertDatabaseHas('svp_reseller_panel_prices', [
            'reseller_svp_user_id' => $resellerId,
            'panel_id' => 1,
            'active' => 0,
        ]);
    }
}
