<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** §7.6 admin/state camelCase keys regression (v15). */
class AdminStateSchemaTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    /** @return array<string, array{0: string, 1: list<string>}> */
    public static function tabSchemaProvider(): array
    {
        return [
            'dashboard' => ['dashboard', ['overview', 'stats']],
            'users' => ['users', ['users', 'pagination']],
            'monitoring' => ['monitoring', ['monitorHosts', 'overview']],
            'xui_panels' => ['xui_panels', ['panels', 'pagination']],
            'receipts' => ['receipts', ['receipts']],
        ];
    }

    /** @dataProvider tabSchemaProvider */
    public function test_admin_state_tab_has_expected_top_level_keys(string $tab, array $keys): void
    {
        $json = $this->actingAsAdmin()
            ->getJson('/api/v1/admin/state?tab='.$tab)
            ->assertOk()
            ->json();

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $json, "Missing key {$key} for tab {$tab}");
        }
    }
}
