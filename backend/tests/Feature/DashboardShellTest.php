<?php

namespace Tests\Feature;

use Tests\TestCase;

class DashboardShellTest extends TestCase
{
    public function test_dashboard_login_page_injects_boot_object(): void
    {
        $response = $this->get('/dashboard/login');

        $response->assertOk();
        $response->assertSee('__SIMPLEVPBOT_DASH__', false);
        $response->assertSee('restUrl', false);
        $response->assertSee('isLoggedIn', false);
    }
}
