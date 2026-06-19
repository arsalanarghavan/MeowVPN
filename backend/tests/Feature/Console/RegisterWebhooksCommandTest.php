<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class RegisterWebhooksCommandTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => true], 200)]);
    }

    public function test_dry_run_exits_success(): void
    {
        $code = Artisan::call('svp:register-webhooks', ['--dry-run' => true]);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Dry run', Artisan::output());
    }
}
