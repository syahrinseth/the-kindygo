<?php

namespace App\Filament\Resources\Payments\Pages;

use Filament\Actions\CreateAction;
use App\Models\Payment;
use App\Filament\Resources\Payments\Payments\PaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn () => Auth::user()->can('create', Payment::class)),
        ];
    }
}
