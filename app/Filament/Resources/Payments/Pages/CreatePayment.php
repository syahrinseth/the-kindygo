<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Payments\Payments\PaymentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set tenant_id to the current tenant
        $data['tenant_id'] = Auth::user()->current_tenant_id;

        // Set the default status to PENDING if not specified
        $data['status'] = $data['status'] ?? PaymentStatus::PENDING->value;

        return $data;
    }
}
