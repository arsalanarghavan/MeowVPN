<?php

namespace Tests\Feature\Core;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LoggingChannelsTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function svpChannelProvider(): array
    {
        return [
            'svp' => ['svp'],
            'svp-webhook' => ['svp-webhook'],
            'svp-panel' => ['svp-panel'],
            'svp-relay' => ['svp-relay'],
        ];
    }

    /** @dataProvider svpChannelProvider */
    public function test_svp_log_channel_is_configured(string $channel): void
    {
        $config = config("logging.channels.{$channel}");
        $this->assertIsArray($config);
        $this->assertSame('daily', $config['driver'] ?? null);
        $this->assertStringContainsString('svp', (string) ($config['path'] ?? ''));
    }

    /** @dataProvider svpChannelProvider */
    public function test_svp_log_channel_accepts_write(string $channel): void
    {
        Log::channel($channel)->info('logging_channels_test', ['channel' => $channel]);
        $this->assertTrue(true);
    }
}
