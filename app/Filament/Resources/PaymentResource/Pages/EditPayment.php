<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\PaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditPayment extends EditRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->visible(fn () => Auth::user()->can('view', $this->record)),
            DeleteAction::make()
                ->visible(fn () => Auth::user()->can('delete', $this->record)),
        ];
    }
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // We don't allow changing tenant_id
        unset($data['tenant_id']);
        
        return $data;
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
