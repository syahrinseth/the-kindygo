<?php

namespace App\Filament\Parent\Resources\InvoiceResource\Pages;

use App\Filament\Parent\Resources\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions\Action;
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
     * Line items are displayed in the invoice statement and are not editable by parents.
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
            Action::make('download_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn (Invoice $record): string => route('invoice.download-pdf', $record))
                ->openUrlInNewTab()
                ->visible(fn (Invoice $record) => Auth::user()->can('view', $record)),

            Action::make('make_payment')
                ->label('Make Payment')
                ->icon('heroicon-o-credit-card')
                ->color('primary')
                ->url(fn (Invoice $record): string => route('filament.parent.pages.make-payment', ['invoice' => $record->id]))
                ->visible(fn (Invoice $record) => Auth::user()->can('view', $record) && $record->balance > 0),
        ];
    }
}
