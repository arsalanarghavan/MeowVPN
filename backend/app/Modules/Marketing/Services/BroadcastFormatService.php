<?php

namespace App\Modules\Marketing\Services;

class BroadcastFormatService
{
    public function sanitizeComposeHtml(string $text): string
    {
        $t = str_replace(["\r\n", "\r"], "\n", $text);
        $t = preg_replace('/<\/div>\s*<div[^>]*>/i', "\n", $t) ?? $t;
        $t = preg_replace('/<div[^>]*>/i', '', $t) ?? $t;
        $t = preg_replace('/<\/div>/i', "\n", $t) ?? $t;
        $t = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n", $t) ?? $t;
        $t = preg_replace('/<p[^>]*>/i', '', $t) ?? $t;
        $t = preg_replace('/<\/p>/i', "\n", $t) ?? $t;
        $t = preg_replace('/<br\s*\/?>/i', "\n", $t) ?? $t;

        $allowed = '<b><strong><i><em><u><ins><s><strike><del><code><pre><a><blockquote><tg-spoiler><span>';
        $t = strip_tags($t, $allowed);
        $t = preg_replace('/<br\s*\/?>/i', "\n", $t) ?? $t;
        $t = preg_replace("/\n{3,}/", "\n\n", $t) ?? $t;

        return trim($t);
    }

    public function htmlToPlain(string $html): string
    {
        $t = $html;
        $t = preg_replace('/<\/p>\s*/i', "\n\n", $t) ?? $t;
        $t = preg_replace('/<br\s*\/?>/i', "\n", $t) ?? $t;
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $t) ?? $t);
    }

    public function formatForBaleMarkdown(string $canonical): string
    {
        $html = trim($canonical);
        if ($html === '') {
            return '';
        }

        $md = $html;
        $md = preg_replace('/<(b|strong)>(.*?)<\/\1>/is', '**$2**', $md) ?? $md;
        $md = preg_replace('/<(i|em)>(.*?)<\/\1>/is', '*$2*', $md) ?? $md;
        $md = preg_replace('/<code>(.*?)<\/code>/is', '`$1`', $md) ?? $md;
        $md = preg_replace('/<a\s+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', '[$2]($1)', $md) ?? $md;
        $md = $this->htmlToPlain($md);
        $md = preg_replace("/\n{3,}/", "\n\n", $md) ?? $md;

        return trim($md);
    }
}
