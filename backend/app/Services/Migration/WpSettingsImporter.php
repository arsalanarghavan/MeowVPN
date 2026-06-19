<?php

namespace App\Services\Migration;

use App\Services\SettingsStore;

class WpSettingsImporter
{
    public function __construct(
        protected SettingsStore $settings,
        protected WpOptionsDecoder $decoder,
        protected SensitiveSettings $sensitive,
    ) {}

    /** @param  array<string, mixed>  $options */
    public function import(array $options, bool $dryRun = false): int
    {
        $count = 0;
        $blob = $options['simplevpbot_settings'] ?? null;
        if ($blob !== null) {
            $decoded = $this->decoder->decode($blob);
            if (is_array($decoded)) {
                foreach ($decoded as $key => $value) {
                    $count += $this->setKey((string) $key, $value, $dryRun);
                }
            }
        }

        foreach ($options as $name => $raw) {
            if (! is_string($name) || ! str_starts_with($name, 'simplevpbot_')) {
                continue;
            }
            if ($name === 'simplevpbot_settings') {
                continue;
            }
            $key = substr($name, strlen('simplevpbot_'));
            $count += $this->setKey($key, $this->decoder->decode($raw), $dryRun);
        }

        return $count;
    }

    protected function setKey(string $key, mixed $value, bool $dryRun): int
    {
        if ($key === '') {
            return 0;
        }
        if ($dryRun) {
            return 1;
        }
        $this->settings->set($key, $value);

        return 1;
    }
}
