<?php

namespace Tests\Feature\Migration;

use App\Services\Migration\WpImportService;
use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class WpImportMinimalTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    public function test_wp_import_dry_run_parses_options(): void
    {
        $this->createSvpSchema();
        $path = base_path('tests/fixtures/wp-minimal.sql');
        $res = app(WpImportService::class)->run($path, [
            'dry_run' => true,
            'default_password' => 'temp-pass-12345',
        ]);

        $this->assertTrue($res['ok']);
        $this->assertGreaterThan(0, $res['settings_keys']);
    }

    public function test_wp_import_writes_settings(): void
    {
        $this->createSvpSchema();
        $path = base_path('tests/fixtures/wp-minimal.sql');
        $res = app(WpImportService::class)->run($path, [
            'force' => true,
            'default_password' => 'temp-pass-12345',
        ]);

        $this->assertTrue($res['ok']);
        $this->assertSame('Imported Site', app(SettingsStore::class)->get('site_name'));
    }
}
