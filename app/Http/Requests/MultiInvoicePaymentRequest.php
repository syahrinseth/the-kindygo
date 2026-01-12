<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MultiInvoicePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'invoice_ids' => ['required', 'array', 'min:1', 'max:10'],
            'invoice_ids.*' => [
                'required',
                'integer',
                Rule::exists('invoices', 'id')->where(function ($query) {
                    $query->where('tenant_id', auth()->user()->current_tenant_id);
                }),
                function ($attribute, $value, $fail) {
                    $invoice = Invoice::find($value);
                    if ($invoice && $invoice->getRemainingBalance() <= 0) {
                        $fail("Invoice {$invoice->number} has no outstanding balance.");
                    }
                },
            ],
            'payment_amount' => [
                'required',
                'integer',
                'min:100',
                function ($attribute, $value, $fail) {
                    if (isset($this->invoice_ids) && is_array($this->invoice_ids)) {
                        $invoices = Invoice::whereIn('id', $this->invoice_ids)->get();
                        $totalBalance = $invoices->sum(fn ($inv) => $inv->getRemainingBalance());

                        if ($value > $totalBalance) {
                            $fail('Payment amount cannot exceed the total outstanding balance of selected invoices (RM '.number_format($totalBalance / 100, 2).').');
                        }
                    }
                },
            ],
            'gateway' => ['required', 'string', Rule::in(['bank_transfer', 'chip'])],
            'reference_no' => ['required_if:gateway,bank_transfer', 'nullable', 'string', 'max:255'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoice_ids.required' => 'Please select at least one invoice to pay.',
            'invoice_ids.max' => 'You can only select up to 10 invoices per payment.',
            'payment_amount.required' => 'Payment amount is required.',
            'payment_amount.min' => 'Minimum payment amount is RM 1.00.',
            'gateway.required' => 'Payment gateway is required.',
            'reference_no.required_if' => 'Reference number is required for bank transfer payments.',
            'payment_proof.mimes' => 'Payment proof must be a JPG, PNG, or PDF file.',
            'payment_proof.max' => 'Payment proof file size must not exceed 5MB.',
        ];
    }
}
