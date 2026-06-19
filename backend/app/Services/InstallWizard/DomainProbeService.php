<?php

namespace App\Services\InstallWizard;

use App\Services\SettingsStore;
use Illuminate\Support\Facades\Http;

class DomainProbeService
{
    public function __construct(protected SettingsStore $settings) {}

    /** @return list<array<string, mixed>> */
    public function probeAll(): array
    {
        $targets = $this->domainTargets();
        $out = [];
        foreach ($targets as $target) {
            $out[] = $this->probeTarget($target);
        }

        return $out;
    }

    /** @return list<array{key: string, label: string, url: string, health_path: string}> */
    public function domainTargets(): array
    {
        $targets = [
            [
                'key' => 'core',
                'label' => 'Core API',
                'url' => rtrim((string) $this->settings->get('public_site_url', ''), '/'),
                'health_path' => '/health/ready',
            ],
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'url' => rtrim((string) $this->settings->get('dashboard_public_url', ''), '/'),
                'health_path' => '/',
            ],
            [
                'key' => 'telegram',
                'label' => 'Telegram bot',
                'url' => rtrim((string) $this->settings->get('telegram_public_webhook_base', ''), '/'),
                'health_path' => '/health',
            ],
            [
                'key' => 'bale',
                'label' => 'Bale bot',
                'url' => rtrim((string) $this->settings->get('bale_public_webhook_base', ''), '/'),
                'health_path' => '/health',
            ],
            [
                'key' => 'relay',
                'label' => 'Relay',
                'url' => rtrim((string) $this->settings->get('telegram_relay_public_url', ''), '/'),
                'health_path' => '/health',
            ],
        ];

        return array_values(array_filter($targets, fn (array $t) => $t['url'] !== ''));
    }

    /** @param  array{key: string, label: string, url: string, health_path: string}  $target */
    /** @return array<string, mixed> */
    public function probeTarget(array $target): array
    {
        $url = $target['url'];
        $host = parse_url($url, PHP_URL_HOST);
        $dnsOk = false;
        $dnsError = '';
        if (is_string($host) && $host !== '') {
            $resolved = @gethostbyname($host);
            $dnsOk = $resolved !== $host || filter_var($host, FILTER_VALIDATE_IP);
            if (! $dnsOk) {
                $dnsError = 'dns_unresolved';
            }
        } else {
            $dnsError = 'invalid_url';
        }

        $probeUrl = $url.$target['health_path'];
        $httpOk = false;
        $statusCode = 0;
        $httpError = '';
        $hints = [];

        if ($dnsOk) {
            try {
                $response = Http::timeout(12)->withOptions(['verify' => true])->get($probeUrl);
                $statusCode = $response->status();
                $httpOk = $response->successful() || ($target['key'] === 'relay' && in_array($statusCode, [200, 404], true));
                if (! $httpOk) {
                    $httpError = 'http_'.$statusCode;
                    $hints[] = 'Check nginx/ssl and that the service container is running.';
                }
            } catch (\Throwable $e) {
                $httpError = 'connection_failed';
                $hints[] = $e->getMessage();
                $hints[] = 'Verify firewall, DNS A/AAAA records, and TLS certificate.';
            }
        } else {
            $hints[] = 'Point DNS for this hostname to this server IP.';
        }

        if ($target['key'] === 'core' && ! $httpOk && $dnsOk) {
            $hints[] = 'Core should respond at /health/ready.';
        }

        return [
            'key' => $target['key'],
            'label' => $target['label'],
            'url' => $url,
            'probe_url' => $probeUrl,
            'dns_ok' => $dnsOk,
            'dns_error' => $dnsError,
            'http_ok' => $httpOk,
            'status_code' => $statusCode,
            'http_error' => $httpError,
            'ok' => $dnsOk && $httpOk,
            'hints' => $hints,
        ];
    }

    /** @return array<string, string> */
    public function currentDomainUrls(): array
    {
        return [
            'core_url' => rtrim((string) $this->settings->get('public_site_url', ''), '/'),
            'dashboard_url' => rtrim((string) $this->settings->get('dashboard_public_url', ''), '/'),
            'telegram_url' => rtrim((string) $this->settings->get('telegram_public_webhook_base', ''), '/'),
            'bale_url' => rtrim((string) $this->settings->get('bale_public_webhook_base', ''), '/'),
            'relay_url' => rtrim((string) $this->settings->get('telegram_relay_public_url', ''), '/'),
        ];
    }

    /** @param  array<string, string>  $urls */
    public function hostReconfigureRequired(array $urls): bool
    {
        $snapshot = app(InstallWizardService::class)->hostsSnapshot();
        if ($snapshot === []) {
            return false;
        }
        foreach (['core_url', 'dashboard_url', 'telegram_url', 'bale_url', 'relay_url'] as $key) {
            $new = rtrim((string) ($urls[$key] ?? ''), '/');
            $old = rtrim((string) ($snapshot[$key] ?? ''), '/');
            if ($new !== '' && $old !== '' && $new !== $old) {
                $newHost = parse_url($new, PHP_URL_HOST);
                $oldHost = parse_url($old, PHP_URL_HOST);
                if ($newHost !== $oldHost) {
                    return true;
                }
            }
        }

        return false;
    }
}
