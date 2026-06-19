<?php

namespace Tests\Unit;

use App\Modules\XuiPanel\Services\XuiClient;
use Tests\TestCase;

class XuiClientIpsTest extends TestCase
{
    public function test_parse_client_ips_response_legacy_string(): void
    {
        $client = new XuiClient;
        $ips = $client->parseClientIpsResponse(['obj' => '1.1.1.1,2.2.2.2']);
        $this->assertSame(['1.1.1.1', '2.2.2.2'], $ips);
    }

    public function test_parse_client_ips_response_v3_objects(): void
    {
        $client = new XuiClient;
        $ips = $client->parseClientIpsResponse([
            'obj' => [
                ['ip' => '10.0.0.1'],
                ['Ip' => '10.0.0.2'],
            ],
        ]);
        $this->assertSame(['10.0.0.1', '10.0.0.2'], $ips);
    }
}
