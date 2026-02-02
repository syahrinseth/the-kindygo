<?php

namespace App\Http\Requests\API\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Form request for the deprecated single-step registration endpoint.
 *
 * @deprecated This request class is used by the deprecated POST /api/v1/auth/register
 *             endpoint which now returns 410 Gone. For new parent registrations,
 *             use the multi-step registration flow starting at POST /api/v1/auth/register/step-1
 *             with RegisterStep1Request instead.
 * @see \App\Http\Requests\API\V1\RegisterStep1Request For the current registration flow
 * @see \App\Http\Controllers\API\V1\RegistrationController For the multi-step registration controller
 */
class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'tenant_slug' => ['nullable', 'string', 'exists:tenants,slug'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
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
            'name.required' => 'Your name is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'tenant_slug.exists' => 'The specified organisation does not exist.',
        ];
    }
}
