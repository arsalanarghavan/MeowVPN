<?php

namespace App\Services\Migration;

class WpOptionsDecoder
{
    public function decode(mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }
        if (is_array($raw)) {
            return $raw;
        }

        $str = trim((string) $raw);
        if ($str === '') {
            return '';
        }

        if ($this->looksSerialized($str)) {
            $val = @unserialize($str, ['allowed_classes' => false]);
            if ($val !== false || $str === 'b:0;') {
                return $val;
            }
        }

        $json = json_decode($str, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        }

        return $str;
    }

    protected function looksSerialized(string $str): bool
    {
        return preg_match('/^[aOsibdN]:/', $str) === 1;
    }
}
