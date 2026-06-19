<?php

namespace Tests\Unit;

use Tests\TestCase;

/** ADMIN_TAB_KEYS includes reseller routable tabs (v19). */
class NavTabParityTest extends TestCase
{
    public function test_admin_tab_keys_includes_reseller_routable_tabs(): void
    {
        $path = base_path('../frontend/src/config/admin-nav.ts');
        $this->assertFileExists($path);
        $src = file_get_contents($path);
        $this->assertIsString($src);
        $this->assertStringContainsString('reseller_charge', $src);
        $this->assertStringContainsString('reseller_settings', $src);
        $this->assertStringContainsString('FEATURE_TAB_MAP', $src);
    }
}
