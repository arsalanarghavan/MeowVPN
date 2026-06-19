<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** Reseller forbidden on admin-only HTTP routes (v14). */
class ResellerAdminOnlyRoutesTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('backup', true);
    }

    public function test_reseller_forbidden_on_audit(): void
    {
        $this->actingAsReseller()->getJson('/api/v1/admin/audit')
            ->assertForbidden();
    }

    public function test_reseller_forbidden_on_logs(): void
    {
        $this->actingAsReseller()->getJson('/api/v1/admin/logs')
            ->assertForbidden();
    }

    public function test_reseller_forbidden_on_backups(): void
    {
        $this->actingAsReseller()->getJson('/api/v1/admin/backups')
            ->assertForbidden();
    }

    public function test_reseller_forbidden_on_purge_expired(): void
    {
        $this->actingAsReseller()->getJson('/api/v1/admin/purge-expired')
            ->assertForbidden();
    }
}
