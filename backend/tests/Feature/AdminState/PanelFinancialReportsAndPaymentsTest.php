<?php

namespace Tests\Feature\AdminState;

use App\Models\DashboardUser;
use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class PanelFinancialReportsAndPaymentsTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
        $this->setModuleEnabled('xui_panel', true);
    }

    public function test_panel_financial_reports_tab_returns_payload(): void
    {
        $admin = DashboardUser::query()->where('username', 'admin')->first();

        DB::table('svp_transactions')->insert([
            'user_id' => 1,
            'service_id' => 0,
            'amount' => 150000,
            'type' => 'purchase',
            'status' => 'approved',
            'meta_json' => json_encode(['plan_id' => 1, 'volume_gb' => 50]),
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson(
            '/api/v1/admin/state?activeTab=panel_financial_reports&date_from='.now()->startOfMonth()->toDateString()
            .'&date_to='.now()->toDateString().'&calendar=jalali'
        );

        $response->assertOk();
        $payload = $response->json('panelFinancialReports');
        $this->assertIsArray($payload);
        $this->assertSame('jalali', $payload['period']['calendar'] ?? null);
        $this->assertArrayHasKey('summary', $payload);
        $this->assertArrayHasKey('rows', $payload);
        $this->assertGreaterThanOrEqual(0, (float) ($payload['summary']['sales_toman'] ?? -1));
    }

    public function test_payments_tab_transactions_view(): void
    {
        $admin = DashboardUser::query()->where('username', 'admin')->first();

        DB::table('svp_transactions')->insert([
            'user_id' => 1,
            'service_id' => 0,
            'amount' => 1000,
            'type' => 'purchase',
            'status' => 'approved',
            'meta_json' => json_encode(['payment_method' => 'c2c']),
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson(
            '/api/v1/admin/state?activeTab=payments&payments_view=transactions'
        );

        $response->assertOk()
            ->assertJsonPath('paymentsView', 'transactions');
        $this->assertIsArray($response->json('payments'));
        $this->assertIsArray($response->json('paymentAggregates'));
        $this->assertIsArray($response->json('orders'));
    }

    public function test_receipts_tab_aliases_to_payments_hub(): void
    {
        $admin = DashboardUser::query()->where('username', 'admin')->first();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/state?activeTab=receipts');

        $response->assertOk()
            ->assertJsonPath('paymentsView', 'receipts')
            ->assertJsonStructure(['receipts', 'payments', 'orders', 'paymentsView']);
    }

    public function test_live_stream_requires_auth(): void
    {
        $this->getJson('/api/v1/admin/live-stream')->assertUnauthorized();
    }

    public function test_live_stream_authenticated_starts_sse(): void
    {
        $admin = DashboardUser::query()->where('username', 'admin')->first();

        $response = $this->actingAs($admin)->get('/api/v1/admin/live-stream');

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));
    }

    public function test_settings_tab_landing_and_subscription_portal_and_cron(): void
    {
        $admin = DashboardUser::query()->where('username', 'admin')->first();

        $this->actingAs($admin)->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'landing',
            'landing_enabled' => true,
            'landing_hero_title' => 'MeowVPN',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->actingAs($admin)->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'subscription_portal',
            'portal_subscription_template' => 'classic',
            'portal_datepicker' => 'jalali',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->actingAs($admin)->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'cron',
            'live_metrics_poll_seconds' => 20,
            'live_sse_push_seconds' => 5,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertNotEmpty((string) DB::table('svp_settings')->where('key_name', 'landing_enabled')->value('value'));
        $this->assertSame('classic', (string) DB::table('svp_settings')->where('key_name', 'portal_subscription_template')->value('value'));
        $this->assertSame('20', (string) DB::table('svp_settings')->where('key_name', 'live_metrics_poll_seconds')->value('value'));
    }
}
