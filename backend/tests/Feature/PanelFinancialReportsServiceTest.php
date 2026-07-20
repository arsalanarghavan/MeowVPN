<?php

namespace Tests\Feature;

use App\Services\PanelFinancialReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelFinancialReportsServiceTest extends TestCase
{
    public function test_build_returns_array_structure(): void
    {
        /** @var PanelFinancialReportsService $svc */
        $svc = $this->app->make(PanelFinancialReportsService::class);
        $out = $svc->build([
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'calendar' => 'gregorian',
        ]);

        $this->assertIsArray($out);
    }
}
