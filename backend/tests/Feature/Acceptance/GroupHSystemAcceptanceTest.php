<?php

namespace Tests\Feature\Acceptance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §14 Group H — System */
class GroupHSystemAcceptanceTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_audit_filter_and_pagination(): void
    {
        if (! Schema::hasTable('svp_audit_log')) {
            Schema::create('svp_audit_log', function ($table) {
                $table->id();
                $table->string('domain', 32)->nullable();
                $table->string('event_type', 64)->nullable();
                $table->string('actor_label')->nullable();
                $table->text('payload_json')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
        DB::table('svp_audit_log')->insert([
            'domain' => 'admin',
            'event_type' => 'mutate.test',
            'actor_label' => 'admin',
            'payload_json' => '{}',
            'created_at' => now(),
        ]);

        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/audit?domain=admin&q=mutate')
            ->assertOk()
            ->assertJsonStructure(['rows', 'pagination']);
    }

    public function test_l2tp_crud(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'l2tp_add',
            'label' => 'L2TP Test',
            'ssh_host' => '10.0.0.2',
            'l2tp_host' => 'l2tp.test',
        ])->assertOk();
    }

    public function test_health_deep_endpoint(): void
    {
        $this->getJson('/health/deep')
            ->assertOk();
    }

    public function test_backup_list_admin(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/backups')
            ->assertOk();
    }

    public function test_metrics_endpoint(): void
    {
        $this->get('/metrics')
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; version=0.0.4; charset=utf-8');
    }
}
