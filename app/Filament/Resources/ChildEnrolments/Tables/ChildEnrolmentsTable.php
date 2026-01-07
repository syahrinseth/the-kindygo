<?php

namespace App\Filament\Resources\ChildEnrolments\Tables;

use App\Enums\ChildEnrolmentBilledEvery;
use App\Enums\ChildEnrolmentStatus;
use App\Enums\ChildEnrolmentType;
use App\Models\ChildEnrolment;
use App\Models\Product;
use App\Services\ChildEnrolmentInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ChildEnrolmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('child.full_name')
                    ->label('Child')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('centre.name')
                    ->label('Centre')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('additional_products_count')
                    ->label('Additional Products')
                    ->getStateUsing(function (ChildEnrolment $record): string {
                        $additionalProducts = $record->additional_products ?? [];
                        $count = count($additionalProducts);

                        if ($count === 0) {
                            return 'None';
                        }

                        return $count.' product'.($count > 1 ? 's' : '');
                    })
                    ->badge()
                    ->color(function (ChildEnrolment $record): string {
                        $count = count($record->additional_products ?? []);

                        return $count > 0 ? 'info' : 'gray';
                    })
                    ->tooltip(function (ChildEnrolment $record): ?string {
                        $additionalProducts = $record->additional_products ?? [];

                        if (empty($additionalProducts)) {
                            return null;
                        }

                        $productNames = [];
                        foreach ($additionalProducts as $item) {
                            if (isset($item['product_id'])) {
                                $product = Product::find($item['product_id']);
                                if ($product) {
                                    $billingFreq = isset($item['billed_every'])
                                        ? ucwords(str_replace('_', ' ', $item['billed_every']))
                                        : 'Monthly';
                                    $productNames[] = "{$product->name} ({$billingFreq})";
                                }
                            }
                        }

                        return implode(', ', $productNames);
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'draft' => 'gray',
                        'inactive' => 'gray',
                        'completed' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => ucfirst($state->value))
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state): string => ucwords(str_replace('_', ' ', $state->value)))
                    ->sortable(),

                TextColumn::make('billed_every')
                    ->label('Billing Frequency')
                    ->formatStateUsing(fn ($state): string => ucwords(str_replace('_', ' ', $state->value)))
                    ->sortable(),

                TextColumn::make('next_bill_date')
                    ->label('Next Billing')
                    ->date('d M Y')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('date_start')
                    ->label('Start Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('date_end')
                    ->label('End Date')
                    ->date()
                    ->placeholder('Ongoing')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->getStateUsing(fn (ChildEnrolment $record): bool => $record->isActive())
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('centre_id')
                    ->label('Centre')
                    ->relationship('centre', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->options(ChildEnrolmentStatus::options())
                    ->multiple(),

                SelectFilter::make('type')
                    ->options(ChildEnrolmentType::options())
                    ->multiple(),

                SelectFilter::make('billed_every')
                    ->label('Billing Frequency')
                    ->options(ChildEnrolmentBilledEvery::options())
                    ->multiple(),

                Filter::make('active_only')
                    ->label('Active Enrolments')
                    ->query(fn (Builder $query): Builder => $query->where('status', ChildEnrolmentStatus::ACTIVE))
                    ->toggle(),

                Filter::make('current_only')
                    ->label('Current Enrolments')
                    ->query(fn (Builder $query): Builder => $query->current())
                    ->toggle(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->visible(fn (ChildEnrolment $record): bool => Auth::user()->can('view', $record)),
                    EditAction::make()
                        ->visible(fn (ChildEnrolment $record): bool => Auth::user()->can('update', $record)),
                    Action::make('generate_invoices')
                        ->label('Generate Invoices')
                        ->icon('heroicon-o-document-plus')
                        ->color('success')
                        ->action(function (ChildEnrolment $record) {
                            $invoiceService = app(ChildEnrolmentInvoiceService::class);
                            $enrolments = $invoiceService->getRelatedEnrolments($record);
                            if (empty($enrolments)) {
                                Notification::make()
                                    ->title('No Invoices Needed')
                                    ->body('All enrolments for this parent at this centre already have current invoices.')
                                    ->warning()
                                    ->send();

                                return;
                            }
                            $invoices = $invoiceService->generateInvoicesForEnrolments($enrolments);

                            $childNames = $enrolments->map(fn ($e) => $e->child->full_name)->unique()->implode(', ');

                            Notification::make()
                                ->title('Invoices Generated')
                                ->body("Successfully generated {$invoices->count()} invoice(s) for: {$childNames}.")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->visible(fn (ChildEnrolment $record): bool => Auth::user()->can('update', $record)),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
