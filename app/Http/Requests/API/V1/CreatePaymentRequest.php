<?php

namespace App\Http\Requests\API\V1;

use App\Enums\Gateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentRequest extends FormRequest
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
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['required', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'gateway' => ['nullable', 'string', Rule::enum(Gateway::class)],
            'allocation' => ['nullable', 'array'],
            'allocation.*' => ['integer', 'min:0'],
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
            'invoice_ids.required' => 'At least one invoice is required.',
            'invoice_ids.min' => 'At least one invoice is required.',
            'invoice_ids.*.exists' => 'One or more invoices do not exist.',
            'amount.required' => 'Payment amount is required.',
            'amount.min' => 'Payment amount must be greater than zero.',
            'gateway.in' => 'Invalid payment gateway selected.',
        ];
    }
}
