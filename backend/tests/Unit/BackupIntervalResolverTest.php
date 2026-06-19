<?php

namespace Tests\Unit;

use App\Services\BackupIntervalResolver;
use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupIntervalResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_reads_flat_and_nested_settings_keys(): void
    {
        $store = app(SettingsStore::class);
        $store->set('backup.backup_interval_minutes', 90);
        $this->assertSame(90, app(BackupIntervalResolver::class)->minutes());

        $store->set('backup_interval_minutes', 120);
        $this->assertSame(120, app(BackupIntervalResolver::class)->minutes());
    }

    public function test_clamps_to_spec_bounds(): void
    {
        app(SettingsStore::class)->set('backup_interval_minutes', 2);
        $this->assertSame(5, app(BackupIntervalResolver::class)->minutes());

        app(SettingsStore::class)->set('backup_interval_minutes', 5000);
        $this->assertSame(1440, app(BackupIntervalResolver::class)->minutes());
    }
}
