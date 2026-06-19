<?php

namespace Tests\Feature\Backup;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class BackupModuleGateTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('backup', false);
    }

    public function test_backup_routes_forbidden_when_module_disabled(): void
    {
        $this->actingAsAdmin()
            ->getJson('/api/v1/admin/backups')
            ->assertStatus(403)
            ->assertJsonPath('message', 'module_disabled');
    }
}
