<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** §14 H.3 — audit filter, pagination (v18). */
class AuditControllerFilterTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        DB::table('svp_audit_log')->insert([
            [
                'domain' => 'security',
                'event_type' => 'impersonation.start',
                'actor_kind' => 'admin',
                'actor_wp_user_id' => 0,
                'actor_svp_user_id' => 0,
                'target_type' => 'user',
                'target_id' => 100,
                'reseller_scope_id' => 100,
                'payload_json' => '{}',
                'ip_hash' => '',
                'created_at' => now()->subHour(),
            ],
            [
                'domain' => 'admin',
                'event_type' => 'user_status',
                'actor_kind' => 'admin',
                'actor_wp_user_id' => 0,
                'actor_svp_user_id' => 0,
                'target_type' => 'user',
                'target_id' => 101,
                'reseller_scope_id' => 0,
                'payload_json' => '{}',
                'ip_hash' => '',
                'created_at' => now(),
            ],
        ]);
    }

    public function test_audit_filter_by_domain_and_event_type(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/audit?domain=security&event_type=impersonation.start')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'rows');
    }

    public function test_audit_search_query(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/audit?q=impersonation')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_audit_pagination(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/audit?page=1&per_page=1')
            ->assertOk()
            ->assertJsonPath('pagination.perPage', 1)
            ->assertJsonPath('pagination.total', 2);
    }
}
