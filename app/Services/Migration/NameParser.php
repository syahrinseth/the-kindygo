<?php

namespace App\Services\Migration;

class NameParser
{
    /**
     * Split a full name into first_name and last_name.
     * Last word becomes last_name, everything else becomes first_name.
     *
     * @return array{first_name: string, last_name: string}
     */
    public static function split(string $fullname): array
    {
        $parts = explode(' ', trim($fullname));

        if (count($parts) === 1) {
            return ['first_name' => $parts[0], 'last_name' => ''];
        }

        $lastName = array_pop($parts);
        $firstName = implode(' ', $parts);

        return ['first_name' => $firstName, 'last_name' => $lastName];
    }
}
