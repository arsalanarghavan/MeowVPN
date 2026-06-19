<?php

namespace Tests\Unit;

use App\Modules\ModuleManager;
use Tests\TestCase;

class ResellerDependsAnyModuleTest extends TestCase
{
    public function test_reseller_requires_telegram_or_bale(): void
    {
        config([
            'modules.modules.telegram.enabled' => false,
            'modules.modules.bale.enabled' => false,
            'modules.modules.reseller.enabled' => true,
        ]);
        app()->forgetInstance(ModuleManager::class);

        $this->assertFalse(app(ModuleManager::class)->isEnabled('reseller'));
    }

    public function test_reseller_enabled_when_bale_on(): void
    {
        config([
            'modules.modules.telegram.enabled' => false,
            'modules.modules.bale.enabled' => true,
            'modules.modules.reseller.enabled' => true,
        ]);
        app()->forgetInstance(ModuleManager::class);

        $this->assertTrue(app(ModuleManager::class)->isEnabled('reseller'));
    }
}
