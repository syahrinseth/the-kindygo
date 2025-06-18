<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Filament\Resources\InvoiceResource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class InvoiceListWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Invoices';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 2;
    
    public static function canView(): bool
    {
        return Auth::user()->can('viewInvoiceList', \App\Filament\Pages\FinanceDashboard::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Invoice::forCurrentUser()
                    ->latest('date')
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('total')
                    ->money('MYR', 100)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (InvoiceStatus $state): string => match ($state) {
                        InvoiceStatus::DRAFT => 'gray',
                        InvoiceStatus::PENDING => 'warning',
                        InvoiceStatus::PAID => 'success',
                        InvoiceStatus::OVERDUE => 'danger',
                        InvoiceStatus::CANCELLED => 'gray',
                    }),
            ])
            ->actions([
                Action::make('view')
                    ->url(fn (Invoice $record): string => InvoiceResource::getUrl('view', ['record' => $record]))
                    ->icon('heroicon-o-eye'),
            ])
            ->paginated(false);
    }
}