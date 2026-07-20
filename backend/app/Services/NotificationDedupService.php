<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NotificationDedupService
{
    /** @var array<string, string> */
    public const SCOPE_OPTIONS = [
        'purge_expired' => 'simplevpbot_purge_expired_sent_buckets',
        'expiry' => 'simplevpbot_expiry_sent_buckets',
        'marketing' => 'simplevpbot_marketing_sent_buckets',
    ];

    public function __construct(protected SettingsStore $settings) {}

    public function optionName(string $scope): string
    {
        $scope = Str::slug($scope);

        return self::SCOPE_OPTIONS[$scope] ?? '';
    }

    public function transientKey(string $scope, string $bucketKey): string
    {
        return 'svp_nd_'.Str::slug($scope).'_'.md5($bucketKey);
    }

    public function claim(string $scope, string $bucketKey, int $ttlDays = 90): bool
    {
        $opt = $this->optionName($scope);
        $bucketKey = (string) $bucketKey;
        if ($opt === '' || $bucketKey === '') {
            return false;
        }
        $ttlSecs = max(86400, $ttlDays * 86400);
        $tKey = $this->transientKey($scope, $bucketKey);
        if (Cache::has($tKey)) {
            return false;
        }
        $sent = (array) $this->settings->get($opt, []);
        if (! empty($sent[$bucketKey])) {
            Cache::put($tKey, (int) $sent[$bucketKey], $ttlSecs);

            return false;
        }
        Cache::put($tKey, time(), $ttlSecs);
        $this->markOption($scope, $bucketKey);

        return true;
    }

    public function markOption(string $scope, string $bucketKey): void
    {
        $opt = $this->optionName($scope);
        $bucketKey = (string) $bucketKey;
        if ($opt === '' || $bucketKey === '') {
            return;
        }
        $sent = (array) $this->settings->get($opt, []);
        $sent[$bucketKey] = time();
        $this->settings->set($opt, $sent);
    }

    public function wasSent(string $scope, string $bucketKey): bool
    {
        if (Cache::has($this->transientKey($scope, $bucketKey))) {
            return true;
        }
        $opt = $this->optionName($scope);
        if ($opt === '') {
            return false;
        }
        $sent = (array) $this->settings->get($opt, []);

        return ! empty($sent[(string) $bucketKey]);
    }
}
