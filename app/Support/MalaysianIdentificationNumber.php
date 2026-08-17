<?php

namespace App\Support;

class MalaysianIdentificationNumber
{
    public static function format(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $digits = preg_replace('/\D/', '', $value);

        if (strlen($digits) !== 12) {
            return $value;
        }

        return sprintf('%s-%s-%s', substr($digits, 0, 6), substr($digits, 6, 2), substr($digits, 8, 4));
    }
}
