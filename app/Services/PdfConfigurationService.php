<?php

namespace App\Services;

use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\PdfBuilder;

class PdfConfigurationService
{
    /**
     * Apply standardized Browsershot configuration to a PDF builder.
     *
     * @param PdfBuilder $pdf
     * @return PdfBuilder
     */
    public function applyBrowsershotConfig(PdfBuilder $pdf): PdfBuilder
    {
        return $pdf->withBrowsershot(function (Browsershot $browsershot) {
            $this->configureBrowsershot($browsershot);
        });
    }

    /**
     * Configure Browsershot with standard settings.
     *
     * @param Browsershot $browsershot
     * @return void
     */
    public function configureBrowsershot(Browsershot $browsershot): void
    {
        $browsershot
            ->setOption('args', [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-gpu',
                '--disable-software-rasterizer',
                '--disable-features=site-per-process'
            ])
            ->noSandbox()
            ->timeout(300)
            ->writeOptionsToFile();

        // Apply environment-specific configurations
        if (env('NODE_PATH')) {
            $browsershot->setNodeBinary(env('NODE_PATH'));
        }

        if (env('NPM_PATH')) {
            $browsershot->setNpmBinary(env('NPM_PATH'));
        }

        if (env('CHROME_PATH')) {
            $browsershot->setChromePath(env('CHROME_PATH'));
        }
        
        // Additional performance and compatibility settings
        $browsershot->dismissDialogs();
        
        // Set user agent to avoid detection issues
        $browsershot->userAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/91.0.4472.124 Safari/537.36');
        
        // Ensure proper PDF generation settings
        $browsershot->showBackground();
        $browsershot->emulateMedia('print');
    }

    /**
     * Get standard PDF margins.
     *
     * @return array
     */
    public function getStandardMargins(): array
    {
        return [10, 10, 10, 10]; // top, right, bottom, left
    }

    /**
     * Get standard PDF format.
     *
     * @return string
     */
    public function getStandardFormat(): string
    {
        return 'a4';
    }

    /**
     * Create a standardized PDF builder with common settings.
     *
     * @param string $view
     * @param array $data
     * @return PdfBuilder
     */
    public function createStandardPdf(string $view, array $data = []): PdfBuilder
    {
        $pdf = \Spatie\LaravelPdf\Facades\Pdf::view($view, $data)
            ->format($this->getStandardFormat())
            ->margins(...$this->getStandardMargins());

        return $this->applyBrowsershotConfig($pdf);
    }
}
