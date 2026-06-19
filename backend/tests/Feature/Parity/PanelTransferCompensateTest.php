<?php

namespace Tests\Feature\Parity;

use App\Modules\XuiPanel\Services\ServicePanelTransferService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/** Port of tests/PanelTransferCompensateTest.php — Laravel transfer compensation */
class PanelTransferCompensateTest extends TestCase
{
    public function test_transfer_service_has_compensation_methods(): void
    {
        $ref = new ReflectionClass(ServicePanelTransferService::class);
        $this->assertTrue($ref->hasMethod('deleteTargetClient'));
        $this->assertTrue($ref->hasMethod('transferOne'));
        $source = file_get_contents($ref->getFileName());
        $this->assertStringContainsString('transfer_db_failed', $source);
        $this->assertStringContainsString('deleteTargetClient', $source);
    }
}
