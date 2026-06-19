<?php

namespace App\Modules\XuiPanel\Services;

use App\Services\PanelSecretCipher;
use Illuminate\Support\Facades\DB;

class XuiPanelContext
{
    public const FLAVOR_UNKNOWN = 'unknown';

    public const FLAVOR_LEGACY = 'legacy_inbound';

    public const FLAVOR_V3 = 'v3_clients';

    public int $panelId = 0;

    /** @var array<string, mixed> */
    public array $panel = [];

    public string $resolvedAuthBase = '';

    public string $lastAuthFlow = '';

    /** @var array<string, string> */
    private static array $cachedFlavor = [];

    /** @return array<string, mixed> */
    public static function loadPanel(int $panelId): array
    {
        if ($panelId < 1) {
            return [];
        }
        $row = DB::table('svp_panels')->where('id', $panelId)->first();

        return $row ? (array) $row : [];
    }

    public static function normalizePanelUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        if ($url !== '' && preg_match('#/panel$#i', $url)) {
            $url = rtrim((string) preg_replace('#/panel$#i', '', $url), '/');
        }

        return $url;
    }

    /** @param  array<string, mixed>  $panel */
    public function bind(int $panelId, array $panel = []): void
    {
        $this->panelId = max(0, $panelId);
        $this->panel = $panel !== [] ? $panel : self::loadPanel($this->panelId);
        $this->resolvedAuthBase = '';
        $this->lastAuthFlow = '';
    }

    /** @return array<string, mixed> */
    public function credentials(): array
    {
        $norm = fn ($raw) => self::normalizePanelUrl((string) $raw);
        $root = $norm((string) ($this->panel['panel_url'] ?? ''));

        $cipher = app(PanelSecretCipher::class);

        return [
            'panel_url' => $root !== '' ? $root.'/' : '',
            'panel_username' => (string) ($this->panel['panel_username'] ?? ''),
            'panel_password' => $cipher->decrypt($this->panel['panel_password'] ?? null),
            'panel_api_base' => (string) ($this->panel['panel_api_base'] ?? 'panel/api'),
            'panel_login_secret' => $cipher->decrypt($this->panel['panel_login_secret'] ?? null),
            'panel_api_token' => $cipher->decrypt($this->panel['panel_api_token'] ?? null),
            'panel_api_flavor' => (string) ($this->panel['panel_api_flavor'] ?? self::FLAVOR_UNKNOWN),
            'subscription_public_base' => (string) ($this->panel['subscription_public_base'] ?? ''),
        ];
    }

    public function panelRoot(): string
    {
        return (string) ($this->credentials()['panel_url'] ?? '');
    }

    public function apiRoot(): string
    {
        $base = trim((string) ($this->credentials()['panel_api_base'] ?? 'panel/api'), " \t\n\r/");

        return $base === '' ? $this->panelRoot() : rtrim($this->panelRoot(), '/').'/'.$base.'/';
    }

    public function hasApiToken(): bool
    {
        return trim((string) ($this->credentials()['panel_api_token'] ?? '')) !== '';
    }

    public function hasCookieCredentials(): bool
    {
        $c = $this->credentials();

        return trim((string) ($c['panel_username'] ?? '')) !== ''
            && trim((string) ($c['panel_password'] ?? '')) !== ''
            && $this->panelRoot() !== '';
    }

    public function getApiFlavor(bool $refresh = false): string
    {
        $key = 'p'.$this->panelId;
        if (! $refresh && isset(self::$cachedFlavor[$key])) {
            return self::$cachedFlavor[$key];
        }
        $stored = trim((string) ($this->credentials()['panel_api_flavor'] ?? self::FLAVOR_UNKNOWN));
        if (! $refresh && $stored !== '' && $stored !== self::FLAVOR_UNKNOWN) {
            self::$cachedFlavor[$key] = $stored;

            return $stored;
        }

        return self::FLAVOR_UNKNOWN;
    }

    public function setApiFlavor(string $flavor): void
    {
        $key = 'p'.$this->panelId;
        self::$cachedFlavor[$key] = $flavor;
        if ($this->panelId > 0) {
            DB::table('svp_panels')->where('id', $this->panelId)->update(['panel_api_flavor' => $flavor]);
            $this->panel['panel_api_flavor'] = $flavor;
        }
    }

    public function isV3ClientsApi(): bool
    {
        return self::FLAVOR_V3 === $this->getApiFlavor();
    }
}
