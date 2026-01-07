<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tnc_accepted' => ['required', 'accepted', 'boolean'],
            'undertaking_accepted' => ['required', 'accepted', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'tnc_accepted.required' => 'You must accept the Terms and Conditions to continue.',
            'tnc_accepted.accepted' => 'You must accept the Terms and Conditions to continue.',
            'undertaking_accepted.required' => 'You must accept the Letter of Undertaking to continue.',
            'undertaking_accepted.accepted' => 'You must accept the Letter of Undertaking to continue.',
        ];
    }
}
