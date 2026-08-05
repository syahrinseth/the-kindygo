<?php

use App\Enums\ProductType;

it('provides a badge colour for every product type', function () {
    $expectedColours = [
        ProductType::SERVICE->value => 'primary',
        ProductType::FEE->value => 'info',
        ProductType::PRODUCT->value => 'secondary',
        ProductType::PROGRAMME->value => 'success',
        ProductType::ANNUAL_FEE->value => 'danger',
        ProductType::OTHERS->value => 'gray',
        ProductType::EVENT->value => 'info',
        ProductType::MERCHANDISE->value => 'secondary',
        ProductType::OVERTIME->value => 'warning',
        ProductType::STAYIN->value => 'primary',
        ProductType::DEPOSIT->value => 'danger',
    ];

    expect(ProductType::cases())->toHaveCount(count($expectedColours));

    foreach ($expectedColours as $type => $colour) {
        expect(ProductType::from($type)->getBadgeColor())->toBe($colour);
    }
});
