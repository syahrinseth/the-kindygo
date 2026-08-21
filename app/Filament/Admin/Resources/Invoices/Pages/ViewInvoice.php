<?php

namespace App\Filament\Admin\Resources\Invoices\Pages;

use App\Filament\Admin\Resources\Invoices\Actions\DownloadInvoicePdfAction;
use App\Filament\Admin\Resources\Invoices\Actions\MakePaymentAction;
use App\Filament\Admin\Resources\Invoices\Actions\SubmitToEInvoiceAction;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string
    {
        return $this->record->number;
    }

    public function getSubheading(): ?string
    {
        return collect([
            $this->record->centre?->name,
            $this->record->user?->name,
        ])->filter()->join(' · ');
    }

    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.admin.resources.invoices.invoice-statement')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Line items are managed from the invoice edit page.
     *
     * @return array<int, class-string>
     */
    public function getRelationManagers(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            DownloadInvoicePdfAction::makeHeaderAction(),
            SubmitToEInvoiceAction::makeHeaderAction(),
            MakePaymentAction::makeHeaderAction(),
            EditAction::make()
                ->visible(fn (): bool => Auth::user()->can('update', $this->record)),
        ];
    }
}
