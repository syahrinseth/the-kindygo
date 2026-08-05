<?php

use App\Console\Commands\MigrateLegacyQuotations;

it('exposes the legacy quotation migration command', function () {
    $command = app(MigrateLegacyQuotations::class);

    expect($command->getName())->toBe('migrate:legacy-quotations')
        ->and($command->getDescription())->toContain('1_quotations');
});
