<?php

namespace App\Http\Requests\API\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterDeviceTokenRequest extends FormRequest
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
            'device_token' => ['required', 'string', 'max:500'],
            'device_type' => ['required', 'string', Rule::in(['ios', 'android', 'web'])],
            'device_name' => ['nullable', 'string', 'max:255'],
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
            'device_token.required' => 'Device token is required for push notifications.',
            'device_token.max' => 'Device token is too long.',
            'device_type.required' => 'Device type is required.',
            'device_type.in' => 'Device type must be ios, android, or web.',
        ];
    }
}
