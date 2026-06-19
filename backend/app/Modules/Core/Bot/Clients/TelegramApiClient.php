<?php

namespace App\Modules\Core\Bot\Clients;

use App\Modules\Relay\Services\TelegramRelayService;

class TelegramApiClient extends AbstractPlatformClient
{
    protected function baseUrl(): string
    {
        if (svp_modules()->isEnabled('relay')) {
            $relay = app(TelegramRelayService::class);
            if ($relay->isEnabled()) {
                return $relay->botApiBaseUrl($this->token);
            }
        }

        return 'https://api.telegram.org/bot'.rawurlencode($this->token).'/';
    }
}
