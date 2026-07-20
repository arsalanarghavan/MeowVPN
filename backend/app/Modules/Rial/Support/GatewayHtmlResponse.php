<?php

namespace App\Modules\Rial\Support;

use Illuminate\Http\Response;

class GatewayHtmlResponse
{
    public static function make(string $title, string $message, bool $success = true): Response
    {
        $color = $success ? '#15803d' : '#b91c1c';
        $html = '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>'.e($title).'</title></head>'
            .'<body style="font-family:Tahoma,sans-serif;padding:2rem;text-align:center;background:#f8fafc">'
            .'<h1 style="color:'.e($color).'">'.e($title).'</h1>'
            .'<p style="font-size:1.1rem;line-height:1.7">'.e($message).'</p>'
            .'</body></html>';

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
