<?php

use App\Models\Tenant;
use App\Services\Payments\TenantChipService;

it('refuses to create a CHIP client for an unconfigured tenant', function (): void {
    $tenant = Tenant::factory()->create();

    expect(fn (): mixed => app(TenantChipService::class)->getPurchase($tenant, 'purchase_123'))
        ->toThrow(RuntimeException::class, 'CHIP payments are not configured for this organisation.');
});

it('requires both CHIP values before a tenant is considered configured', function (): void {
    $tenant = Tenant::factory()->create(['chip_brand_id' => 'brand_123']);

    expect($tenant->hasChipCredentials())->toBeFalse();

    $tenant->update(['chip_api_key' => 'secret-key']);

    expect($tenant->fresh()->hasChipCredentials())->toBeTrue();
});
