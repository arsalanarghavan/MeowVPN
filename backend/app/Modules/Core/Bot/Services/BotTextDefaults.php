<?php

namespace App\Modules\Core\Bot\Services;

/**
 * Default bot text pack (fa/en) — seeded from WP Bot_Text_Defaults (+ Extended).
 */
class BotTextDefaults
{
    /** @var list<array{key_name:string,category:string,locale:string,value:string}>|null */
    protected static ?array $rows = null;

    /** @var array<string, array{fa:string,en:string}>|null */
    protected static ?array $valuesMap = null;

    /** @var array<string, string>|null */
    protected static ?array $categories = null;

    /** @return list<array{key_name:string,category:string,locale:string,value:string}> */
    public static function allRows(): array
    {
        if (self::$rows !== null) {
            return self::$rows;
        }

        $path = database_path('data/bot_text_defaults.php');
        $rows = is_file($path) ? require $path : [];
        self::$rows = is_array($rows) ? array_values($rows) : [];

        return self::$rows;
    }

    /** @return array<string, array{fa:string,en:string}> */
    public static function valuesMap(): array
    {
        if (self::$valuesMap !== null) {
            return self::$valuesMap;
        }

        $out = [];
        foreach (self::allRows() as $row) {
            $kn = (string) ($row['key_name'] ?? '');
            if ($kn === '') {
                continue;
            }
            if (! isset($out[$kn])) {
                $out[$kn] = ['fa' => '', 'en' => ''];
            }
            $loc = ((string) ($row['locale'] ?? 'fa')) === 'en' ? 'en' : 'fa';
            $out[$kn][$loc] = (string) ($row['value'] ?? '');
        }
        self::$valuesMap = $out;

        return self::$valuesMap;
    }

    /** @return array{fa:string,en:string} */
    public static function defaultPairForKey(string $key): array
    {
        $map = self::valuesMap();

        return $map[$key] ?? ['fa' => '', 'en' => ''];
    }

    public static function categoryForKey(string $key): string
    {
        if (self::$categories === null) {
            $cats = [];
            foreach (self::allRows() as $row) {
                $kn = (string) ($row['key_name'] ?? '');
                if ($kn === '' || isset($cats[$kn])) {
                    continue;
                }
                $cats[$kn] = (string) ($row['category'] ?? 'general');
            }
            self::$categories = $cats;
        }

        return self::$categories[$key] ?? 'general';
    }

    /** @return array{key_name:string,category:string,value:string,locale:string}|null */
    public static function defaultRowForKey(string $keyName, string $locale = 'fa'): ?array
    {
        $k = trim($keyName);
        if ($k === '') {
            return null;
        }
        $loc = $locale === 'en' ? 'en' : 'fa';
        foreach (self::allRows() as $row) {
            if ((string) ($row['key_name'] ?? '') === $k && ((string) ($row['locale'] ?? 'fa')) === $loc) {
                return [
                    'key_name' => $k,
                    'category' => (string) ($row['category'] ?? 'general'),
                    'value' => (string) ($row['value'] ?? ''),
                    'locale' => $loc,
                ];
            }
        }

        return null;
    }

    public static function normalizeLocale(?string $locale): string
    {
        return strtolower(trim((string) $locale)) === 'en' ? 'en' : 'fa';
    }

    public static function clearCache(): void
    {
        self::$rows = null;
        self::$valuesMap = null;
        self::$categories = null;
    }
}
