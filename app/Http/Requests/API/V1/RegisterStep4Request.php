<?php

namespace App\Http\Requests\API\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for Step 4 of multi-step registration.
 *
 * Validates agreement acceptance for terms & conditions and letter of undertaking.
 * Both must be accepted (true) to complete registration.
 */
class RegisterStep4Request extends FormRequest
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
            'tnc_accepted' => ['required', 'boolean', 'accepted'],
            'undertaking_accepted' => ['required', 'boolean', 'accepted'],
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
            'tnc_accepted.required' => 'You must accept the Terms and Conditions.',
            'tnc_accepted.accepted' => 'You must accept the Terms and Conditions to complete registration.',
            'undertaking_accepted.required' => 'You must accept the Letter of Undertaking.',
            'undertaking_accepted.accepted' => 'You must accept the Letter of Undertaking to complete registration.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tnc_accepted' => 'Terms and Conditions acceptance',
            'undertaking_accepted' => 'Letter of Undertaking acceptance',
        ];
    }
}
