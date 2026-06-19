<?php

namespace App\Modules\Bale\Mutations;

use App\Services\SettingsStore;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Http;

class BaleMutations
{
    public function __construct(protected SettingsStore $settings) {}

    /** @return array<string, array{0: class-string, 1: string}> */
    public function handlers(): array
    {
        return [
            'bot_test_bale' => [self::class, 'botTestBale'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function botTestBale(array $payload, ?Authenticatable $actor): array
    {
        $token = (string) $this->settings->get('bale_bot_token', $this->settings->get('bale_token', ''));
        if ($token === '') {
            return svp_err('Bale token not configured');
        }
        $r = Http::get("https://tapi.bale.ai/bot{$token}/getMe");

        return svp_ok(['bale' => $r->json()]);
    }
}
