<?php

namespace App\Filament\Admin\Resources\ChildEnrolments\Pages;

use App\Enums\ChildEnrolmentStatus;
use App\Filament\Admin\Resources\ChildEnrolments\ChildEnrolmentResource;
use App\Services\ChildEnrolmentInvoiceService;
use Exception;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateChildEnrolment extends CreateRecord
{
    protected static string $resource = ChildEnrolmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Automatically set the tenant_id based on the current user
        $user = Auth::user();
        $data['tenant_id'] = $user->current_tenant_id;

        // Only auto-set centre_id if it's not already provided and user has a current centre
        if (empty($data['centre_id'])) {
            $currentCentre = $user->tenants()
                ->where('tenant_id', $user->current_tenant_id)
                ->first()
                ?->pivot
                ?->current_centre_id;

            if ($currentCentre) {
                $data['centre_id'] = $currentCentre;
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        if ($this->record->status === ChildEnrolmentStatus::ACTIVE) {
            $invoiceService = app(ChildEnrolmentInvoiceService::class);
            try {
                $invoices = $invoiceService->generateInvoicesForEnrolment($this->record);

                if ($invoices->count() > 0) {
                    Notification::make()
                        ->title('Invoices Generated')
                        ->body("Successfully generated {$invoices->count()} invoice(s) for this enrolment.")
                        ->success()
                        ->send();
                }
            } catch (Exception $e) {
                Notification::make()
                    ->title('Invoice Generation Failed')
                    ->body('There was an error generating invoices: '.$e->getMessage())
                    ->warning()
                    ->send();
            }
        }
    }
}
