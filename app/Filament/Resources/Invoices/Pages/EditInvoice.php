<?php

namespace App\Filament\Resources\Invoices\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Enums\InvoiceStatus;
use Filament\Actions;
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
        
        // Calculate the total if not specified
        if (!isset($data['total']) || $data['total'] == 0) {
            $data['total'] = ($data['total_amount'] ?? 0) - ($data['total_discounts'] ?? 0);
        }
        
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
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
