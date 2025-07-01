<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\PdfConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use function Spatie\LaravelPdf\Support\pdf;

class InvoicePdfController extends Controller
{
    protected PdfConfigurationService $pdfConfig;

    public function __construct(PdfConfigurationService $pdfConfig)
    {
        $this->pdfConfig = $pdfConfig;
    }

    public function download(Invoice $invoice)
    {
        // Check if user can access this invoice
        $user = Auth::user();
        
        // Super Admin, Admin, Principal can download invoices from their associated centres
        if ($user->hasAnyRole(['Super Admin', 'Admin', 'Principal'])) {
            // For Super Admin and Admin, check if invoice is from their tenant
            if ($user->hasAnyRole(['Super Admin', 'Admin'])) {
                if ($invoice->tenant_id !== $user->current_tenant_id) {
                    abort(403, 'Unauthorized access to invoice.');
                }
            }
            
            // For Principal, check if invoice is from centres they're associated with
            if ($user->hasRole('Principal') && $invoice->centre_id) {
                if ($invoice->tenant_id !== $user->current_tenant_id ||
                    !$user->centres()->where('centres.id', $invoice->centre_id)->exists()) {
                    abort(403, 'Unauthorized access to invoice.');
                }
            }
        }
        // Parent and Teacher can only download their own invoices
        elseif ($user->hasAnyRole(['Parent', 'Teacher'])) {
            if ($invoice->user_id !== $user->id || $invoice->tenant_id !== $user->current_tenant_id) {
                abort(403, 'Unauthorized access to invoice.');
            }
        }
        else {
            abort(403, 'Unauthorized access to invoice.');
        }

        // Sanitize filename by replacing invalid characters
        $sanitizedNumber = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $invoice->number);
        $filename = "invoice-{$sanitizedNumber}.pdf";

        // Generate and return the PDF as download
        return pdf()
            ->view('pdf.invoice', ['invoice' => $invoice])
            ->format($this->pdfConfig->getStandardFormat())
            ->margins(...$this->pdfConfig->getStandardMargins())
            ->name($filename)
            ->withBrowsershot(function ($browsershot) {
                $this->pdfConfig->configureBrowsershot($browsershot);
            })
            ->download();
    }
}
