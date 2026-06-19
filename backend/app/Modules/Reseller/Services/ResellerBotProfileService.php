<?php

namespace App\Modules\Reseller\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ResellerBotProfileService
{
    public function findByReseller(int $resellerId): ?object
    {
        if ($resellerId < 1 || ! Schema::hasTable('svp_reseller_bot_profiles')) {
            return null;
        }

        return DB::table('svp_reseller_bot_profiles')
            ->where('reseller_svp_user_id', $resellerId)
            ->first();
    }

    public function ensureProfile(int $resellerId): object
    {
        $profile = $this->findByReseller($resellerId);
        if ($profile) {
            return $profile;
        }

        $id = DB::table('svp_reseller_bot_profiles')->insertGetId([
            'reseller_svp_user_id' => $resellerId,
            'enabled' => true,
            'telegram_enabled' => true,
            'bale_enabled' => true,
            'webhook_secret' => '',
            'updated_at' => now(),
        ]);

        return DB::table('svp_reseller_bot_profiles')->where('id', $id)->first();
    }

    /** @param  array<string, mixed>  $data */
    public function saveProfile(int $resellerId, array $data): object
    {
        $allowed = [
            'brand_name', 'logo_url', 'favicon_url', 'theme_primary', 'theme_accent',
            'custom_domain', 'telegram_relay_public_url', 'config_label_override',
            'config_label_prefix', 'telegram_secret_token', 'enabled', 'telegram_enabled',
            'bale_enabled', 'admin_telegram_ids', 'admin_bale_ids', 'bale_wallet_provider_token',
            'text_overrides_json', 'payment_methods_json',
        ];

        $row = collect($data)->only($allowed)->filter(fn ($v) => $v !== null)->all();
        $row['updated_at'] = now();

        $existing = $this->findByReseller($resellerId);
        if ($existing) {
            DB::table('svp_reseller_bot_profiles')
                ->where('reseller_svp_user_id', $resellerId)
                ->update($row);

            return $this->findByReseller($resellerId);
        }

        $row['reseller_svp_user_id'] = $resellerId;
        if (! isset($row['webhook_secret'])) {
            $row['webhook_secret'] = '';
        }

        DB::table('svp_reseller_bot_profiles')->insert($row);

        return $this->findByReseller($resellerId);
    }

    public function tokenForPlatform(object $profile, string $platform): string
    {
        $col = $platform === 'bale' ? 'bale_token' : 'telegram_token';
        $stored = trim((string) ($profile->{$col} ?? ''));

        return $this->decryptToken($stored);
    }

    public function setToken(int $resellerId, string $platform, string $plain): void
    {
        $col = $platform === 'bale' ? 'bale_token' : 'telegram_token';
        $encrypted = $plain === '' ? null : Crypt::encryptString($plain);

        $this->ensureProfile($resellerId);
        DB::table('svp_reseller_bot_profiles')
            ->where('reseller_svp_user_id', $resellerId)
            ->update([
                $col => $encrypted,
                'updated_at' => now(),
            ]);
    }

    public function webhookSecretPlaintext(object $profile): string
    {
        $stored = trim((string) ($profile->webhook_secret ?? $profile->secret ?? ''));
        if ($stored === '') {
            return '';
        }
        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return $stored;
        }
    }

    public function ensureWebhookSecret(int $resellerId): string
    {
        $profile = $this->ensureProfile($resellerId);
        $secret = $this->webhookSecretPlaintext($profile);
        if ($secret !== '') {
            return $secret;
        }

        return $this->rotateWebhookSecret($resellerId);
    }

    public function rotateWebhookSecret(int $resellerId): string
    {
        $secret = Str::random(32);
        $this->ensureProfile($resellerId);
        DB::table('svp_reseller_bot_profiles')
            ->where('reseller_svp_user_id', $resellerId)
            ->update([
                'webhook_secret' => Crypt::encryptString($secret),
                'updated_at' => now(),
            ]);

        return $secret;
    }

    public function saveBotUsername(int $resellerId, string $platform, string $username): void
    {
        $col = $platform === 'bale' ? 'bale_bot_username' : 'telegram_bot_username';
        $this->ensureProfile($resellerId);
        DB::table('svp_reseller_bot_profiles')
            ->where('reseller_svp_user_id', $resellerId)
            ->update([
                $col => $username,
                'updated_at' => now(),
            ]);
    }

    /** @return array<string, mixed>|null */
    public function profileArrayForRuntime(int $resellerId): ?array
    {
        $profile = $this->findByReseller($resellerId);
        if (! $profile) {
            return null;
        }

        $arr = (array) $profile;
        $arr['telegram_token'] = $this->tokenForPlatform($profile, 'telegram');
        $arr['bale_token'] = $this->tokenForPlatform($profile, 'bale');
        $arr['webhook_secret'] = $this->webhookSecretPlaintext($profile);

        return $arr;
    }

    protected function decryptToken(string $stored): string
    {
        if ($stored === '') {
            return '';
        }

        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return $stored;
        }
    }
}
