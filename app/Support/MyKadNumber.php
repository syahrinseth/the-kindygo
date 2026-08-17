<?php

namespace App\Support;

class MyKadNumber
{
    public static function format(?string $value): ?string
    {
        return MalaysianIdentificationNumber::format($value);
    }
}
