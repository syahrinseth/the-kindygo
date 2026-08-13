<?php

use App\Filament\Forms\ChildForm;
use Filament\Schemas\Components\Section;

it('provides basic information as a standalone section', function () {
    $schema = ChildForm::basic();

    expect($schema)
        ->toHaveCount(1)
        ->and($schema[0])->toBeInstanceOf(Section::class)
        ->and($schema[0]->getHeading())->toBe('Basic Information');
});
