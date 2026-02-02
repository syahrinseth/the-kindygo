<?php

namespace App\Http\Requests\API\V1;

use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailRequest extends FormRequest
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
            'code' => ['required_without:token', 'string', 'size:6'],
            'token' => ['required_without:code', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
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
            'code.required_without' => 'Verification code is required when token is not provided.',
            'code.size' => 'Verification code must be exactly 6 characters.',
            'token.required_without' => 'Verification token is required when code is not provided.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
        ];
    }
}
