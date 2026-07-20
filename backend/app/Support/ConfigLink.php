<?php

namespace App\Support;

class ConfigLink
{
    public static function uriFragmentLabel(string $uri): string
    {
        $p = strpos($uri, '#');
        if ($p === false) {
            return '';
        }
        $frag = substr($uri, $p + 1);
        if ($frag === '') {
            return '';
        }

        return trim(rawurldecode($frag));
    }

    public static function replaceUriFragment(string $uri, string $remarkDisplay): string
    {
        $frag = trim($remarkDisplay);
        if ($frag === '' || ! str_contains($uri, '://')) {
            return $uri;
        }
        $enc = rawurlencode($frag);
        $p = strpos($uri, '#');
        if ($p === false) {
            return $uri.'#'.$enc;
        }

        return substr($uri, 0, $p + 1).$enc;
    }
}
