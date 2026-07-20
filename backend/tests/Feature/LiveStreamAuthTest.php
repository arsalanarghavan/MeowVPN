<?php

namespace Tests\Feature;

use Tests\TestCase;

class LiveStreamAuthTest extends TestCase
{
    public function test_live_stream_requires_authentication(): void
    {
        $res = $this->getJson('/api/v1/admin/live-stream');
        $this->assertTrue(in_array($res->status(), [401, 403], true), 'got '.$res->status());
    }
}
