<?php

namespace App\Filament\Admin\Resources\Invoices\Actions;

use App\Models\Invoice;
use Filament\Actions\Action as HeaderAction;

class DownloadInvoicePdfAction
{
    public static function make(): HeaderAction
    {
        return HeaderAction::make('download_pdf')
            ->label('Download PDF')
            ->icon('heroicon-o-document-arrow-down')
            ->color('success')
            ->url(fn (Invoice $record) => route('invoice.download-pdf', $record))
            ->openUrlInNewTab();
    }

    public static function makeHeaderAction(): HeaderAction
    {
        return HeaderAction::make('download_pdf')
            ->label('Download PDF')
            ->icon('heroicon-o-document-arrow-down')
            ->color('success')
            ->url(fn (Invoice $record) => route('invoice.download-pdf', $record))
            ->openUrlInNewTab();
    }
}
