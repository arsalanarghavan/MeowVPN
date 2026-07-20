<?php

namespace App\Modules\PasarGuard\Services;

use App\Services\PanelSecretCipher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PasarGuardPanelContext
{
    public const PROVIDER = 'pasarguard';

    public const FLAVOR = 'pasarguard_v5';

    public int $panelId = 0;

    /** @var array<string, mixed> */
    public array $panel = [];

    public string $lastAuthFlow = '';

    /** @var array<string, mixed> */
    public array $lastAuthDiag = [];

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
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        return rtrim($url, '/');
    }

    public static function isPasarGuard(array|object|null $panel): bool
    {
        if ($panel === null) {
            return false;
        }
        $row = is_array($panel) ? $panel : (array) $panel;
        $provider = strtolower(trim((string) ($row['panel_provider'] ?? '')));

        return $provider === self::PROVIDER;
    }

    public static function providerForId(int $panelId): string
    {
        if ($panelId < 1) {
            return 'xui';
        }
        $row = self::loadPanel($panelId);

        return self::isPasarGuard($row) ? self::PROVIDER : 'xui';
    }

    /** @param  array<string, mixed>  $panel */
    public function bind(int $panelId, array $panel = []): void
    {
        $this->panelId = max(0, $panelId);
        $this->panel = $panel !== [] ? $panel : self::loadPanel($this->panelId);
        $this->lastAuthFlow = '';
        $this->lastAuthDiag = [];
    }

    /** @return array<string, mixed> */
    public function credentials(): array
    {
        $cipher = app(PanelSecretCipher::class);
        $root = self::normalizePanelUrl((string) ($this->panel['panel_url'] ?? ''));

        return [
            'panel_url' => $root,
            'panel_username' => (string) ($this->panel['panel_username'] ?? ''),
            'panel_password' => $cipher->decrypt($this->panel['panel_password'] ?? null),
            'panel_api_base' => (string) ($this->panel['panel_api_base'] ?? 'api'),
            'panel_login_secret' => $cipher->decrypt($this->panel['panel_login_secret'] ?? null),
            'panel_api_token' => $cipher->decrypt($this->panel['panel_api_token'] ?? null),
            'panel_api_flavor' => (string) ($this->panel['panel_api_flavor'] ?? self::FLAVOR),
            'panel_template_required' => (int) ($this->panel['panel_template_required'] ?? 0),
            'subscription_public_base' => (string) ($this->panel['subscription_public_base'] ?? ''),
        ];
    }

    public function panelRoot(): string
    {
        return (string) ($this->credentials()['panel_url'] ?? '');
    }

    public function apiRoot(): string
    {
        $base = trim((string) ($this->credentials()['panel_api_base'] ?? 'api'), " \t\n\r/");
        if ($base === '') {
            $base = 'api';
        }

        return rtrim($this->panelRoot(), '/').'/'.$base;
    }

    public function resolveUrl(string $path): string
    {
        return rtrim($this->apiRoot(), '/').'/'.ltrim($path, '/');
    }

    public function templateRequired(): bool
    {
        return (int) ($this->credentials()['panel_template_required'] ?? 0) === 1;
    }

    public function persistApiToken(string $token): void
    {
        $token = trim($token);
        if ($token === '' || $this->panelId < 1) {
            return;
        }
        $cipher = app(PanelSecretCipher::class);
        $enc = $cipher->encrypt($token);
        $data = ['panel_api_token' => $enc];
        if (Schema::hasColumn('svp_panels', 'panel_api_flavor')) {
            $data['panel_api_flavor'] = self::FLAVOR;
        }
        DB::table('svp_panels')->where('id', $this->panelId)->update($data);
        $this->panel['panel_api_token'] = $enc;
        $this->panel['panel_api_flavor'] = self::FLAVOR;
    }

    public function setApiFlavor(string $flavor = self::FLAVOR): void
    {
        if ($this->panelId < 1 || ! Schema::hasColumn('svp_panels', 'panel_api_flavor')) {
            return;
        }
        DB::table('svp_panels')->where('id', $this->panelId)->update(['panel_api_flavor' => $flavor]);
        $this->panel['panel_api_flavor'] = $flavor;
    }
}
