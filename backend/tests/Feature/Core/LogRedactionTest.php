<?php

namespace Tests\Feature\Core;

use App\Services\AuditLogService;
use App\Models\DashboardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class LogRedactionTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_audit_payload_redacts_secrets(): void
    {
        $actor = DashboardUser::query()->create([
            'username' => 'a',
            'password' => Hash::make('x'),
            'role' => 'admin',
        ]);

        app(AuditLogService::class)->recordIfSensitive(
            'bot_set_webhook',
            ['panel_password' => 'secret123', 'user_id' => 1],
            ['ok' => true],
            $actor,
        );

        $row = \Illuminate\Support\Facades\DB::table('svp_audit_log')->latest('id')->first();
        $this->assertNotNull($row);
        $payload = json_decode((string) $row->payload_json, true);
        $this->assertSame('[redacted]', $payload['panel_password'] ?? null);
    }
}
