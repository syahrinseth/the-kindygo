<?php

namespace App\Filament\Admin\Resources\Payments\Pages;

use App\Filament\Admin\Resources\Payments\Payments\PaymentResource;
use App\Models\Payment;
use Filament\Actions\CreateAction;
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
