<?php

namespace Tests\Feature\Marketing;

use App\Modules\Marketing\Services\MarketingAutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MarketingAutomationTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200)]);
    }

    public function test_run_rule_now_issues_offer_and_discount_code(): void
    {
        $stats = app(MarketingAutomationService::class)->runRuleNow(1, 10);

        $this->assertGreaterThan(0, $stats['processed']);
        $this->assertGreaterThan(0, $stats['sent']);
        $this->assertDatabaseHas('svp_marketing_offers', [
            'rule_id' => 1,
            'svp_user_id' => 1,
            'status' => 'sent',
        ]);
        $this->assertGreaterThan(0, DB::table('svp_discount_codes')->count());
    }

    public function test_marketing_send_manual_creates_offer(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'marketing_send_manual',
            'user_id' => 1,
            'rule_id' => 1,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_marketing_offers', [
            'rule_id' => 1,
            'svp_user_id' => 1,
            'status' => 'sent',
        ]);
    }
}
