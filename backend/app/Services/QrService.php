<?php

namespace App\Services;

use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QRCodeException;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QrService
{
    public const CARD_PADDING = 28;

    public function isAvailable(): bool
    {
        return class_exists(QRCode::class) && extension_loaded('gd');
    }

    public function pngBytes(string $text): ?string
    {
        if (! $this->isAvailable()) {
            Log::debug('qr: library unavailable', [
                'gd' => extension_loaded('gd'),
                'qrcode' => class_exists(QRCode::class),
            ]);

            return null;
        }
        try {
            $qr = new QRCode($this->options());
            $core = $qr->render($text);
            if (! is_string($core) || $core === '') {
                return null;
            }

            return $this->applyCardFrame($core) ?: null;
        } catch (QRCodeException $e) {
            Log::debug('qr: encode failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    public function tempPng(string $text): ?string
    {
        $bin = $this->pngBytes($text);
        if ($bin === null) {
            return null;
        }
        $dir = storage_path('app/svp/tmp');
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return null;
        }
        $file = $dir.'/qr-'.Str::random(8).'.png';
        if (file_put_contents($file, $bin) === false) {
            return null;
        }

        return $file;
    }

    protected function options(): QROptions
    {
        $moduleValues = [
            QRMatrix::M_DARKMODULE => [16, 62, 110],
            QRMatrix::M_DATA_DARK => [32, 118, 188],
            QRMatrix::M_FINDER_DARK => [14, 58, 102],
            QRMatrix::M_FINDER_DOT => [24, 108, 168],
            QRMatrix::M_ALIGNMENT_DARK => [32, 118, 188],
            QRMatrix::M_TIMING_DARK => [70, 142, 198],
            QRMatrix::M_FORMAT_DARK => [32, 118, 188],
            QRMatrix::M_VERSION_DARK => [32, 118, 188],
            QRMatrix::M_QUIETZONE_DARK => [248, 252, 255],
        ];

        return new QROptions([
            'version' => QRCode::VERSION_AUTO,
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_Q,
            'scale' => 6,
            'imageBase64' => false,
            'imageTransparent' => false,
            'imageTransparencyBG' => [248, 252, 255],
            'addQuietzone' => true,
            'quietzoneSize' => 4,
            'pngCompression' => 6,
            'moduleValues' => $moduleValues,
        ]);
    }

    protected function applyCardFrame(string $qrPngBinary): ?string
    {
        if (! extension_loaded('gd')) {
            return $qrPngBinary;
        }
        $src = @imagecreatefromstring($qrPngBinary);
        if ($src === false) {
            return $qrPngBinary;
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        $pad = self::CARD_PADDING;
        $dw = $sw + 2 * $pad;
        $dh = $sh + 2 * $pad;
        $dst = imagecreatetruecolor($dw, $dh);
        if ($dst === false) {
            imagedestroy($src);

            return $qrPngBinary;
        }
        imagealphablending($dst, true);
        for ($y = 0; $y < $dh; $y++) {
            $t = $dh > 1 ? $y / ($dh - 1) : 0;
            $r = (int) round(236 + (252 - 236) * $t);
            $g = (int) round(244 + (254 - 244) * $t);
            $b = 255;
            $blend = imagecolorallocate($dst, $r, $g, $b);
            imageline($dst, 0, $y, $dw, $y, $blend);
        }
        $shadow = imagecolorallocatealpha($dst, 40, 90, 140, 55);
        imagefilledrectangle($dst, $pad - 2, $pad + 3, $pad + $sw + 1, $pad + $sh + 4, $shadow);
        imagecopy($dst, $src, $pad, $pad, 0, 0, $sw, $sh);
        imagedestroy($src);
        $border = imagecolorallocate($dst, 52, 124, 186);
        imagesetthickness($dst, 2);
        imagerectangle($dst, 4, 4, $dw - 5, $dh - 5, $border);
        $inner = imagecolorallocatealpha($dst, 255, 255, 255, 115);
        imagesetthickness($dst, 1);
        imagerectangle($dst, $pad - 6, $pad - 6, $pad + $sw + 5, $pad + $sh + 5, $inner);
        ob_start();
        imagepng($dst, null, 6);
        $out = ob_get_clean();
        imagedestroy($dst);

        return is_string($out) ? $out : null;
    }
}
