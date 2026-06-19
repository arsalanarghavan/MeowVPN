<?php

namespace Tests\Unit;

use App\Modules\ModuleManager;
use Tests\TestCase;

class ModuleBootOrderTest extends TestCase
{
    public function test_boot_order_places_dependencies_before_dependents(): void
    {
        $order = app(ModuleManager::class)->bootOrder();
        $pos = array_flip($order);

        $this->assertArrayHasKey('core', $pos);
        $this->assertLessThan($pos['telegram'], $pos['core']);
        $this->assertLessThan($pos['relay'], $pos['telegram']);
        $this->assertLessThan($pos['relay'], $pos['core']);
    }
}
