<?php

use App\Services\Migration\StatusMapper;

it('maps legacy numeric state ids to canonical codes', function () {
    expect(StatusMapper::state(10))->toBe('10')
        ->and(StatusMapper::state('12'))->toBe('14');
});

it('maps legacy state names and abbreviations to canonical codes', function () {
    expect(StatusMapper::state('SGR'))->toBe('10')
        ->and(StatusMapper::state('Selangor'))->toBe('10')
        ->and(StatusMapper::state('KUL'))->toBe('14')
        ->and(StatusMapper::state('WP KUALA LUMPUR'))->toBe('14')
        ->and(StatusMapper::state('Wilayah Persekutuan'))->toBe('14')
        ->and(StatusMapper::state('Pulau Pinang'))->toBe('07');
});

it('returns null for empty or unknown legacy states', function () {
    expect(StatusMapper::state(null))->toBeNull()
        ->and(StatusMapper::state('N/a'))->toBeNull()
        ->and(StatusMapper::state('Canada'))->toBeNull();
});
