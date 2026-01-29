<?php

namespace App\Filament\Admin\Resources\ChildEnrolments\Schemas;

use App\Enums\ChildEnrolmentBilledEvery;
use App\Enums\ChildEnrolmentStatus;
use App\Enums\ChildEnrolmentType;
use App\Models\Child;
use App\Models\Product;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ChildEnrolmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Enrolment Details')
                ->schema([
                    Select::make('child_id')
                        ->relationship('child', 'first_name')
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (callable $set) {
                            $set('centre_id', null); // Reset centre when child changes
                        })
                        ->disabled(fn (string $operation): bool => $operation === 'edit')
                        ->helperText(
                            fn (string $operation): ?string => $operation === 'edit'
                                ? 'Child cannot be changed after enrolment is created. Create a new enrolment if needed.'
                                : null
                        )
                        ->columnSpan(1),

                    Select::make('centre_id')
                        ->label('Centre')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (callable $set) {
                            $set('product_id', null); // Reset product when centre changes
                        })
                        ->options(function (callable $get) {
                            $childId = $get('child_id');
                            if (! $childId) {
                                return [];
                            }

                            // Get centres associated with the selected child
                            $child = Child::find($childId);
                            if (! $child) {
                                return [];
                            }

                            $centres = $child->centres()->pluck('centres.name', 'centres.id')->toArray();

                            // If no centres associated, return empty array (will show placeholder)
                            return $centres;
                        })
                        ->placeholder(function (callable $get) {
                            $childId = $get('child_id');
                            if (! $childId) {
                                return 'Select a child first';
                            }

                            $child = Child::find($childId);
                            if ($child && $child->centres()->count() === 0) {
                                return 'No centres associated with this child';
                            }

                            return 'Select a centre';
                        })
                        ->helperText(function (callable $get, string $operation) {
                            if ($operation === 'edit') {
                                return 'Centre cannot be changed after enrolment is created. Create a new enrolment if needed.';
                            }

                            $childId = $get('child_id');
                            if (! $childId) {
                                return 'Only centres associated with the selected child will be shown';
                            }

                            $child = Child::find($childId);
                            if ($child && $child->centres()->count() === 0) {
                                return 'This child is not associated with any centres. Please associate the child with a centre first.';
                            }

                            return 'Only centres associated with the selected child are shown';
                        })
                        ->disabled(function (callable $get, string $operation): bool {
                            if ($operation === 'edit') {
                                return true;
                            }

                            if (! $get('child_id')) {
                                return true;
                            }

                            $child = Child::find($get('child_id'));

                            return $child && $child->centres()->count() === 0;
                        })
                        ->rules([
                            function (callable $get) {
                                return function (string $attribute, $value, Closure $fail) use ($get) {
                                    $childId = $get('child_id');
                                    if ($childId && $value) {
                                        $child = Child::find($childId);
                                        if ($child && ! $child->centres()->where('centres.id', $value)->exists()) {
                                            $fail('The selected centre is not associated with the selected child.');
                                        }
                                    }
                                };
                            },
                        ])
                        ->columnSpan(1),

                    Select::make('product_id')
                        ->label('Product')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->options(function (callable $get) {
                            $centreId = $get('centre_id');
                            if (! $centreId) {
                                return [];
                            }

                            // Get products associated with the selected centre
                            // Also include products that have no centres (available for all centres)
                            return Product::where(function ($query) use ($centreId) {
                                $query->whereHas('centres', function ($centreQuery) use ($centreId) {
                                    $centreQuery->where('centres.id', $centreId);
                                })
                                    // Include products with no centre associations (available for all centres)
                                    ->orWhereDoesntHave('centres');
                            })->active()->pluck('name', 'id')->toArray();
                        })
                        ->placeholder(function (callable $get) {
                            $centreId = $get('centre_id');
                            if (! $centreId) {
                                return 'Select a centre first';
                            }

                            return 'Select a product';
                        })
                        ->helperText(function (callable $get, string $operation) {
                            if ($operation === 'edit') {
                                return 'Product cannot be changed after enrolment is created. Create a new enrolment if needed.';
                            }

                            $centreId = $get('centre_id');
                            if (! $centreId) {
                                return 'Products will be filtered based on the selected centre';
                            }

                            return 'Showing products available for the selected centre and products available for all centres';
                        })
                        ->disabled(function (callable $get, string $operation): bool {
                            return $operation === 'edit' || ! $get('centre_id');
                        })
                        ->rules([
                            function (callable $get) {
                                return function (string $attribute, $value, Closure $fail) use ($get) {
                                    $centreId = $get('centre_id');
                                    if ($centreId && $value) {
                                        $product = Product::find($value);
                                        if ($product) {
                                            // Check if product is associated with the centre OR has no centre associations
                                            $hasAccess = $product->centres()->where('centres.id', $centreId)->exists()
                                                || $product->centres()->count() === 0;

                                            if (! $hasAccess) {
                                                $fail('The selected product is not available for the selected centre.');
                                            }
                                        }
                                    }
                                };
                            },
                        ])
                        ->columnSpan(1),

                    Select::make('status')
                        ->options(ChildEnrolmentStatus::options())
                        ->default(ChildEnrolmentStatus::PENDING->value)
                        ->required()
                        ->columnSpan(1),
                ])
                ->columns(2),

            Section::make('Program Details')
                ->schema([
                    Select::make('type')
                        ->options(ChildEnrolmentType::options())
                        ->default(ChildEnrolmentType::FULL_TIME->value)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (callable $set, $state) {
                            // Reset billed_every when type changes
                            $oneTimeOnlyTypes = [
                                ChildEnrolmentType::TRIAL->value,
                                // ChildEnrolmentType::SUMMER_PROGRAM->value,
                                // ChildEnrolmentType::AFTER_SCHOOL->value,
                                // ChildEnrolmentType::HOLIDAY_PROGRAM->value,
                            ];

                            if (in_array($state, $oneTimeOnlyTypes)) {
                                $set('billed_every', ChildEnrolmentBilledEvery::ONE_TIME->value);
                            } else {
                                $set('billed_every', ChildEnrolmentBilledEvery::MONTHLY->value);
                            }
                        })
                        ->columnSpan(1),

                    Select::make('billed_every')
                        ->options(function (callable $get) {
                            $type = $get('type');

                            // Types that should only show ONE_TIME option
                            $oneTimeOnlyTypes = [
                                ChildEnrolmentType::TRIAL->value,
                                // ChildEnrolmentType::SUMMER_PROGRAM->value,
                                // ChildEnrolmentType::AFTER_SCHOOL->value,
                                // ChildEnrolmentType::HOLIDAY_PROGRAM->value,
                            ];

                            if (in_array($type, $oneTimeOnlyTypes)) {
                                return [
                                    ChildEnrolmentBilledEvery::ONE_TIME->value => 'One Time',
                                ];
                            }

                            return ChildEnrolmentBilledEvery::options();
                        })
                        ->default(function (callable $get) {
                            $type = $get('type');

                            // Types that should default to ONE_TIME
                            $oneTimeOnlyTypes = [
                                ChildEnrolmentType::TRIAL->value,
                                ChildEnrolmentType::SUMMER_PROGRAM->value,
                                ChildEnrolmentType::AFTER_SCHOOL->value,
                                ChildEnrolmentType::HOLIDAY_PROGRAM->value,
                            ];

                            if (in_array($type, $oneTimeOnlyTypes)) {
                                return ChildEnrolmentBilledEvery::ONE_TIME->value;
                            }

                            return ChildEnrolmentBilledEvery::MONTHLY->value;
                        })
                        ->required()
                        ->columnSpan(1),
                ])
                ->columns(2),

            Section::make('Duration')
                ->schema([
                    DateTimePicker::make('date_start')
                        ->label('Start Date')
                        ->required()
                        ->default(now())
                        ->columnSpan(1),

                    DateTimePicker::make('date_end')
                        ->label('End Date')
                        ->nullable()
                        ->columnSpan(1),
                ])
                ->columns(2),

            Section::make('Additional Products')
                ->schema([
                    Repeater::make('additional_products')
                        ->schema([
                            Select::make('product_id')
                                ->label('Product')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->options(function (callable $get) {
                                    $centreId = $get('../../centre_id'); // Get centre_id from parent form
                                    if (! $centreId) {
                                        return [];
                                    }

                                    // Get products associated with the selected centre
                                    // Also include products that have no centres (available for all centres)
                                    return Product::where(function ($query) use ($centreId) {
                                        $query->whereHas('centres', function ($centreQuery) use ($centreId) {
                                            $centreQuery->where('centres.id', $centreId);
                                        })
                                            // Include products with no centre associations (available for all centres)
                                            ->orWhereDoesntHave('centres');
                                    })->active()->pluck('name', 'id')->toArray();
                                })
                                ->placeholder(function (callable $get) {
                                    $centreId = $get('../../centre_id');
                                    if (! $centreId) {
                                        return 'Select a centre first';
                                    }

                                    return 'Select a product';
                                })
                                ->disabled(function (callable $get): bool {
                                    return ! $get('../../centre_id');
                                })
                                ->columnSpan(2),

                            Select::make('billed_every')
                                ->label('Billing Frequency')
                                ->options(ChildEnrolmentBilledEvery::options())
                                ->default(ChildEnrolmentBilledEvery::MONTHLY->value)
                                ->required()
                                ->columnSpan(1),

                            DateTimePicker::make('date_start')
                                ->label('Start Date')
                                ->default(now())
                                ->columnSpan(1),

                            DateTimePicker::make('date_end')
                                ->label('End Date')
                                ->nullable()
                                ->columnSpan(1),

                            Textarea::make('notes')
                                ->label('Notes')
                                ->placeholder('Optional notes for this additional product')
                                ->columnSpan(3),
                        ])
                        ->columns(3)
                        ->collapsible()
                        ->collapsed()
                        ->addActionLabel('Add Additional Product')
                        ->reorderableWithButtons()
                        ->defaultItems(0)
                        ->itemLabel(function (array $state): ?string {
                            if (! isset($state['product_id'])) {
                                return 'New Additional Product';
                            }

                            $product = Product::find($state['product_id']);
                            if (! $product) {
                                return 'Additional Product';
                            }

                            $billingFreq = isset($state['billed_every'])
                                ? ucwords(str_replace('_', ' ', $state['billed_every']))
                                : 'Monthly';

                            return "{$product->name} - {$billingFreq}";
                        }),
                ])
                ->collapsible()
                ->collapsed()
                ->description('Add additional products to this enrolment with their own billing frequencies'),
        ]);
    }

    public static function getComponents(): array
    {
        return [
            ...self::configure(Schema::make())->getComponents(),
        ];
    }
}
