<?php

namespace App\Enums;

enum MalaysianState: string
{
    case JOHOR = '01';
    case KEDAH = '02';
    case KELANTAN = '03';
    case MELAKA = '04';
    case NEGERI_SEMBILAN = '05';
    case PAHANG = '06';
    case PULAU_PINANG = '07';
    case PERAK = '08';
    case PERLIS = '09';
    case SELANGOR = '10';
    case TERENGGANU = '11';
    case SABAH = '12';
    case SARAWAK = '13';
    case WP_KUALA_LUMPUR = '14';
    case WP_LABUAN = '15';
    case WP_PUTRAJAYA = '16';

    /**
     * Get the display name for the state.
     */
    public function label(): string
    {
        return match ($this) {
            self::JOHOR => 'Johor',
            self::KEDAH => 'Kedah',
            self::KELANTAN => 'Kelantan',
            self::MELAKA => 'Melaka',
            self::NEGERI_SEMBILAN => 'Negeri Sembilan',
            self::PAHANG => 'Pahang',
            self::PULAU_PINANG => 'Pulau Pinang',
            self::PERAK => 'Perak',
            self::PERLIS => 'Perlis',
            self::SELANGOR => 'Selangor',
            self::TERENGGANU => 'Terengganu',
            self::SABAH => 'Sabah',
            self::SARAWAK => 'Sarawak',
            self::WP_KUALA_LUMPUR => 'Wilayah Persekutuan Kuala Lumpur',
            self::WP_LABUAN => 'Wilayah Persekutuan Labuan',
            self::WP_PUTRAJAYA => 'Wilayah Persekutuan Putrajaya',
        };
    }

    /**
     * Get state name from state code.
     */
    public static function getNameFromCode(?string $code): string
    {
        if (empty($code)) {
            return '';
        }

        return self::tryFrom($code)?->label() ?? $code;
    }

    /**
     * Get all states as an associative array [code => name].
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $state) {
            $options[$state->value] = $state->label();
        }

        return $options;
    }
}
