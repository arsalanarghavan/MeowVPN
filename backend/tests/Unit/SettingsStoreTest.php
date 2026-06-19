<?php

namespace Tests\Unit;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class SettingsStoreTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_set_and_get_round_trip(): void
    {
        $store = app(SettingsStore::class);
        $store->set('test_flag', true);
        $store->set('test_number', 42);
        $store->set('test_string', 'hello');

        $this->assertTrue($store->get('test_flag'));
        $this->assertSame(42, $store->get('test_number'));
        $this->assertSame('hello', $store->get('test_string'));
    }

    public function test_merge_updates_multiple_keys(): void
    {
        $store = app(SettingsStore::class);
        $store->merge(['a' => 1, 'b' => 'two']);

        $this->assertSame(1, $store->get('a'));
        $this->assertSame('two', $store->get('b'));
        $this->assertGreaterThanOrEqual(2, DB::table('svp_settings')->count());
    }
}
