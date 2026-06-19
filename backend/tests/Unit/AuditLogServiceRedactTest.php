<?php

namespace Tests\Unit;

use App\Models\DashboardUser;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AuditLogServiceRedactTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_nested_secrets_are_redacted_in_audit_payload(): void
    {
        $actor = new DashboardUser(['role' => 'admin', 'svp_user_id' => 0]);
        app(AuditLogService::class)->recordIfSensitive('user_status', [
            'user_id' => 101,
            'nested' => ['api_key' => 'secret-key', 'note' => 'visible'],
            'panel_password' => 'pw',
        ], ['ok' => true], $actor);

        $row = DB::table('svp_audit_log')->where('event_type', 'user_status')->first();
        $this->assertNotNull($row);
        $payload = json_decode((string) $row->payload_json, true);
        $this->assertSame('[redacted]', $payload['nested']['api_key']);
        $this->assertSame('visible', $payload['nested']['note']);
        $this->assertSame('[redacted]', $payload['panel_password']);
    }
}
