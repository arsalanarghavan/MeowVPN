<?php

namespace Tests\Feature\Backup;

use App\Modules\Backup\Jobs\BackupJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class BackupJobCronTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_backup_job_calls_artisan_backup_run(): void
    {
        Artisan::spy();
        (new BackupJob)->handle();
        Artisan::shouldHaveReceived('call')->with('svp:backup-run')->once();
    }
}
