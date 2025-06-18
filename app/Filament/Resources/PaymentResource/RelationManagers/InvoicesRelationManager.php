<?php

namespace App\Filament\Resources\PaymentResource\RelationManagers;

use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?string $title = 'Associated Invoices';

    protected static ?string $modelLabel = 'Invoice';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('id')
                    ->label('Invoice')
                    ->options(function () {
                        $user = Auth::user();
                        if (!$user->current_tenant_id) {
                            return [];
                        }
                        
                        return Invoice::where('tenant_id', $user->current_tenant_id)
                            ->where('status', '!=', 'paid')
                            ->get()
                            ->mapWithKeys(function ($invoice) {
                                return [$invoice->id => "{$invoice->number} - {$invoice->user->name} (RM" . number_format($invoice->total / 100, 2) . ")"];
                            });
                    })
                    ->searchable()
                    ->required(),
                    
                Forms\Components\TextInput::make('amount')
                    ->label('Payment Amount')
                    ->required()
                    ->numeric()
                    ->prefix('RM')
                    ->step(0.01)
                    ->minValue(0)
                    ->helperText('Amount applied to this invoice')
                    ->afterStateHydrated(function (Forms\Components\TextInput $component, $state) {
                        // Convert from cents to decimal for display
                        $component->state($state ? number_format($state / 100, 2, '.', '') : '0.00');
                    })
                    ->dehydrateStateUsing(fn ($state) => (int) ($state * 100)),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Invoice Number')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state): string => match ($state->value) {
                        'draft' => 'gray',
                        'pending' => 'warning',
                        'paid' => 'success',
                        'overdue' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('total')
                    ->label('Invoice Total')
                    ->money('MYR')
                    ->formatStateUsing(fn ($state) => $state / 100)
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('pivot.amount')
                    ->label('Payment Amount')
                    ->money('MYR')
                    ->formatStateUsing(fn ($state) => $state / 100)
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('date')
                    ->label('Invoice Date')
                    ->date('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('view_invoice')
                    ->label('View Invoice')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Invoice $record): string => \App\Filament\Resources\InvoiceResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab()
                    ->visible(fn (Invoice $record) => Auth::user()->can('view', $record)),
            ])
            ->bulkActions([]);
    }
}
