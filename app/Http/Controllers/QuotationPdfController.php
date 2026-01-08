<?php

namespace App\Http\Controllers;

use App\Actions\Quotation\GenerateQuotationPdf;
use App\Models\Quotation;
use Illuminate\Support\Facades\Auth;

class QuotationPdfController extends Controller
{
    public function __construct(
        protected GenerateQuotationPdf $generatePdf
    ) {
    }

    public function download(Quotation $quotation)
    {
        // Check if user can access this quotation
        $user = Auth::user();

        // Super Admin can download any quotation
        if ($user->is_super_admin) {
            // Allow access
        }
        // Admin, Principal can download quotations from their tenant
        elseif ($user->hasAnyRole(['Admin', 'Principal'])) {
            if ($quotation->tenant_id !== $user->current_tenant_id) {
                abort(403, 'Unauthorized access to quotation.');
            }

            // For Principal, check if quotation is from centres they're associated with
            if ($user->hasRole('Principal') && $quotation->centre_id) {
                if (!$user->centres()->where('centres.id', $quotation->centre_id)->exists()) {
                    abort(403, 'Unauthorized access to quotation.');
                }
            }
        }
        // Parent can only download their own quotations
        elseif ($user->hasRole('Parent')) {
            if ($quotation->user_id !== $user->id || $quotation->tenant_id !== $user->current_tenant_id) {
                abort(403, 'Unauthorized access to quotation.');
            }
        }
        else {
            abort(403, 'Unauthorized access to quotation.');
        }

        return $this->generatePdf->execute($quotation);
    }
}
