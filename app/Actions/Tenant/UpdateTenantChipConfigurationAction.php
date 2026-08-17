<?php

namespace App\Actions\Tenant;

use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

class UpdateTenantChipConfigurationAction
{
    public function execute(Tenant $tenant, bool $enabled, ?string $brandId = null, ?string $apiKey = null): Tenant
    {
        if (! $enabled) {
            $tenant->update([
                'chip_brand_id' => null,
                'chip_api_key' => null,
            ]);

            return $tenant->refresh();
        }

        $brandId = filled($brandId) ? $brandId : $tenant->chip_brand_id;
        $apiKey = filled($apiKey) ? $apiKey : $tenant->chip_api_key;

        if (! filled($brandId) || ! filled($apiKey)) {
            throw ValidationException::withMessages([
                'chip_api_key' => 'Both the CHIP Brand ID and API key are required to enable CHIP payments.',
            ]);
        }

        $tenant->update([
            'chip_brand_id' => $brandId,
            'chip_api_key' => $apiKey,
        ]);

        return $tenant->refresh();
    }
}
