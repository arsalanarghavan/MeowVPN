<?php

namespace App\Modules\Core\Bot\Clients;

class BaleApiClient extends AbstractPlatformClient
{
    protected function baseUrl(): string
    {
        return 'https://tapi.bale.ai/bot'.rawurlencode($this->token).'/';
    }
}
