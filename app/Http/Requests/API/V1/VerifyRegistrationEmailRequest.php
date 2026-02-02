<?php

namespace App\Http\Requests\API\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for verifying email during multi-step registration.
 *
 * Validates the temporary token and 6-digit verification code.
 */
class VerifyRegistrationEmailRequest extends FormRequest
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
            'temporary_token' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
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
            'temporary_token.required' => 'Temporary token is required.',
            'code.required' => 'Verification code is required.',
            'code.size' => 'Verification code must be exactly 6 digits.',
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
            'temporary_token' => 'temporary token',
            'code' => 'verification code',
        ];
    }
}
