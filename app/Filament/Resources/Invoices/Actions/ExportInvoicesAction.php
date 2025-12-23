<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Models\Invoice;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;
use SplTempFileObject;

class ExportInvoicesAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'export';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Export Selected')
            ->icon('heroicon-o-document-arrow-down')
            ->color('success')
            ->action(function (Collection $records) {
                // Create a CSV writer
                $csv = Writer::createFromFileObject(new SplTempFileObject());
                
                // Add the header row
                $csv->insertOne([
                    'Invoice Number',
                    'Date',
                    'Due Date',
                    'Status',
                    'User',
                    'Centre',
                    'Total Items',
                    'Amount',
                    'Discounts',
                    'Total',
                ]);
                
                // Add the data rows
                foreach ($records as $record) {
                    $csv->insertOne([
                        $record->number,
                        $record->date->format('Y-m-d'),
                        $record->due_at->format('Y-m-d'),
                        $record->status->value,
                        $record->user->name,
                        $record->centre->name,
                        $record->total_items,
                        number_format($record->total_amount / 100, 2),
                        number_format($record->total_discounts / 100, 2),
                        number_format($record->total / 100, 2),
                    ]);
                }
                
                // Create a temporary file to store the CSV
                $tempFile = storage_path('app/temp/invoices_' . now()->format('Y-m-d') . '_' . uniqid() . '.csv');
                
                // Ensure the directory exists
                if (!file_exists(dirname($tempFile))) {
                    mkdir(dirname($tempFile), 0755, true);
                }
                
                // Save the CSV to the temporary file
                file_put_contents($tempFile, $csv->toString());
                
                // Set the CSV response to download the file
                return response()->download(
                    $tempFile,
                    'invoices_' . now()->format('Y-m-d') . '.csv',
                    [
                        'Content-Type' => 'text/csv',
                        'Content-Disposition' => 'attachment; filename="invoices_' . now()->format('Y-m-d') . '.csv"',
                    ]
                )->deleteFileAfterSend(true);
            })
            ->deselectRecordsAfterCompletion();
    }
}
