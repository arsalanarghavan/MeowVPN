<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TextService
{
    /** @var array<string, string> */
    protected array $cache = [];

    public function __construct(protected SettingsStore $settings) {}

    public function get(string $key, string $default = '', ?string $locale = null): string
    {
        $locale = $locale ?? $this->siteDefaultLocale();
        $ck = $key."\x1e".$locale;
        if (array_key_exists($ck, $this->cache)) {
            return $this->cache[$ck];
        }

        $value = $default;
        if (Schema::hasTable('svp_texts')) {
            $row = DB::table('svp_texts')->where('key_name', $key)->first();
            if ($row && (string) $row->value !== '') {
                $value = (string) $row->value;
            }
        }

        $this->cache[$ck] = $value;

        return $value;
    }

    public function getForUser(string $key, ?SvpUser $user, string $default = ''): string
    {
        $locale = $this->localeForUser($user);

        return $this->get($key, $default, $locale);
    }

    /** @param  array<string, string|int|float>  $vars */
    public function format(string $template, array $vars): string
    {
        $out = $template;
        foreach ($vars as $k => $v) {
            $out = str_replace('{'.$k.'}', (string) $v, $out);
        }

        return $out;
    }

    public function localeForUser(?SvpUser $user): string
    {
        $bl = trim((string) ($user?->bot_locale ?? ''));

        return in_array($bl, ['fa', 'en'], true) ? $bl : $this->siteDefaultLocale();
    }

    public function siteDefaultLocale(): string
    {
        $v = trim((string) $this->settings->get('default_bot_locale', 'fa'));

        return $v === 'en' ? 'en' : 'fa';
    }
}
