<?php

namespace App\Services;

use App\Services\Migration\SensitiveSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class SettingsStore
{
    protected const CACHE_KEY = 'svp_settings_all';

    public function __construct(protected SensitiveSettings $sensitive) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function set(string $key, mixed $value): void
    {
        if ($this->sensitive->shouldEncrypt($key)) {
            $encoded = $this->sensitive->encodeValue($key, $value);
        } elseif (is_string($value)) {
            $encoded = $value;
        } else {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => $key],
            ['value' => $encoded, 'updated_at' => now()]
        );

        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, function () {
            $rows = DB::table('svp_settings')->get(['key_name', 'value']);
            $out = [];
            foreach ($rows as $row) {
                $out[$row->key_name] = $this->decodeStored($row->key_name, (string) $row->value);
            }

            return $out;
        });
    }

    /** @param  array<string, mixed>  $patch */
    public function merge(array $patch): void
    {
        foreach ($patch as $key => $value) {
            $this->set($key, $value);
        }
    }

    protected function decodeStored(string $key, string $raw): mixed
    {
        if ($raw === '') {
            return '';
        }
        if ($this->sensitive->shouldEncrypt($key)) {
            try {
                $plain = Crypt::decryptString($raw);
                $decoded = json_decode($plain, true);

                return json_last_error() === JSON_ERROR_NONE ? $decoded : $plain;
            } catch (\Throwable) {
                // legacy plaintext from pre-encrypt imports
            }
        }
        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
    }
}
