<?php

namespace App\Filament\Admin\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => Auth::user()->can('delete', $this->record)),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Invoice details';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Recalculate and update totals from invoice items to ensure accuracy
        $totals = $this->record->recalculateTotals();

        return array_merge($data, $totals);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // We don't allow changing tenant_id
        unset($data['tenant_id']);

        // If the due date is in the past and status is still PENDING, update to OVERDUE
        if (
            isset($data['due_at']) &&
            isset($data['status']) &&
            $data['status'] === InvoiceStatus::PENDING->value &&
            strtotime($data['due_at']) < time()
        ) {
            $data['status'] = InvoiceStatus::OVERDUE->value;
        }

        return $data;
    }
}
