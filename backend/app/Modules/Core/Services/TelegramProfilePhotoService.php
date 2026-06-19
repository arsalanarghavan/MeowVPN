<?php

namespace App\Modules\Core\Services;

use App\Services\SettingsStore;
use Illuminate\Support\Facades\Http;

class TelegramProfilePhotoService
{
    public function __construct(protected SettingsStore $settings) {}

    public function fetchJpegPath(int $tgUserId): ?string
    {
        if ($tgUserId < 1) {
            return null;
        }
        $token = trim((string) $this->settings->get('telegram_bot_token', $this->settings->get('telegram_token', '')));
        if ($token === '') {
            return null;
        }
        $base = 'https://api.telegram.org/bot'.$token.'/';
        $photos = Http::get($base.'getUserProfilePhotos', ['user_id' => $tgUserId, 'limit' => 1])->json();
        if (empty($photos['ok']) || empty($photos['result']['photos'][0][0]['file_id'])) {
            return null;
        }
        $fileId = (string) $photos['result']['photos'][0][0]['file_id'];
        $file = Http::get($base.'getFile', ['file_id' => $fileId])->json();
        if (empty($file['ok']) || empty($file['result']['file_path'])) {
            return null;
        }
        $path = (string) $file['result']['file_path'];
        $bytes = Http::get('https://api.telegram.org/file/bot'.$token.'/'.$path)->body();
        if ($bytes === '') {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'svp_tgav_');
        if ($tmp === false) {
            return null;
        }
        file_put_contents($tmp, $bytes);

        return $tmp;
    }
}
