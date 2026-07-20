<?php

namespace Tests\Unit\Bot;

use App\Modules\Core\Bot\Services\UiActionRegistryService;
use App\Modules\Core\Bot\Services\UiLayoutStudioService;
use App\Services\SettingsStore;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class UiActionRegistryServiceTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }
    public function test_export_for_dashboard_includes_user_main_surface(): void
    {
        $export = UiActionRegistryService::export_for_dashboard();
        $this->assertIsArray($export);
        $this->assertArrayHasKey('surfaces', $export);
        $surfaces = $export['surfaces'];
        $this->assertIsArray($surfaces);
        $this->assertArrayHasKey('user_main', $surfaces);
        $pack = $surfaces['user_main'];
        $this->assertIsArray($pack);
        $this->assertNotEmpty($pack['actions'] ?? []);
        $this->assertNotEmpty($pack['defaultRows'] ?? []);
    }

    public function test_layout_save_merges_single_surface_without_wiping_others(): void
    {
        app(SettingsStore::class)->set('bot_ui_layout', [
            'surfaces' => [
                'admin_main' => [[['id' => 'admin.root.dashboard', 'enabled' => true, 'glass' => false]]],
            ],
        ]);

        $studio = app(UiLayoutStudioService::class);
        $validated = $studio->validateSurfacesPayload([
            'user_main' => [[
                ['id' => 'user.main.buy', 'enabled' => true, 'glass' => false],
            ]],
        ]);
        $this->assertTrue((bool) ($validated['ok'] ?? false));
        $studio->saveSurfaces($validated['surfaces'] ?? []);

        $stored = app(SettingsStore::class)->get('bot_ui_layout', []);
        $this->assertIsArray($stored['surfaces'] ?? null);
        $this->assertArrayHasKey('admin_main', $stored['surfaces']);
        $this->assertArrayHasKey('user_main', $stored['surfaces']);
    }
}
