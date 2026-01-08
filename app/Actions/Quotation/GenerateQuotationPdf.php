<?php

namespace App\Actions\Quotation;

use App\Models\Quotation;
use App\Services\PdfConfigurationService;

use function Spatie\LaravelPdf\Support\pdf;

class GenerateQuotationPdf
{
    public function __construct(
        protected PdfConfigurationService $pdfConfig
    ) {
    }

    public function execute(Quotation $quotation)
    {
        $filename = $this->generateFilename($quotation);

        return pdf()
            ->view('pdf.quotation', ['quotation' => $quotation])
            ->format($this->pdfConfig->getStandardFormat())
            ->margins(...$this->pdfConfig->getStandardMargins())
            ->name($filename)
            ->withBrowsershot(fn ($browsershot) => $this->pdfConfig->configureBrowsershot($browsershot))
            ->download();
    }

    public function generateFilename(Quotation $quotation): string
    {
        $sanitizedNumber = $this->sanitizeFilename($quotation->number);

        return "quotation-{$sanitizedNumber}.pdf";
    }

    private function sanitizeFilename(string $filename): string
    {
        return str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $filename);
    }
}
