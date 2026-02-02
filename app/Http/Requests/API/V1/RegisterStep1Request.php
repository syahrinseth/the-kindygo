<?php

namespace App\Http\Requests\API\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Form request for Step 1 of multi-step parent registration.
 *
 * This endpoint is exclusively for parent user registration. New users
 * registering through this flow will automatically be assigned the "Parent"
 * role via the RegisterParentBasicInfoAction.
 *
 * The multi-step registration flow consists of:
 * - Step 1: Basic info (name, email, password, phone, tenant, centres)
 * - Verify Email: Confirm email with 6-digit code
 * - Step 2: Profile details (address, photos, office info)
 * - Step 3: Children information (optional)
 * - Step 4: Accept terms and conditions
 *
 * Validates basic user information including name, email, password, phone (required),
 * MyKad number, tenant selection, centre selection, and optional device token.
 */
class RegisterStep1Request extends FormRequest
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
            // Basic user information
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'phone' => ['required', 'string', 'max:20'],
            'mykad_number' => ['nullable', 'string', 'max:20'],

            // Tenant and centre selection
            'tenant_slug' => ['required', 'string', 'exists:tenants,slug'],
            'centre_ids' => ['required', 'array', 'min:1'],
            'centre_ids.*' => ['required', 'integer', 'exists:centres,id'],

            // Device information for push notifications
            'device_name' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'string', 'in:ios,android,web'],
            'device_token' => ['nullable', 'string', 'max:500'],
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
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'phone.required' => 'Phone number is required.',
            'phone.max' => 'Phone number must not exceed 20 characters.',
            'tenant_slug.required' => 'Organisation selection is required.',
            'tenant_slug.exists' => 'The specified organisation does not exist.',
            'centre_ids.required' => 'At least one centre must be selected.',
            'centre_ids.min' => 'At least one centre must be selected.',
            'centre_ids.*.exists' => 'One or more selected centres do not exist.',
            'device_type.in' => 'Device type must be ios, android, or web.',
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
            'mykad_number' => 'MyKad number',
            'tenant_slug' => 'organisation',
            'centre_ids' => 'centres',
            'device_name' => 'device name',
            'device_type' => 'device type',
            'device_token' => 'device token',
        ];
    }
}
