<?php

namespace App\Http\Requests\API\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantChipConfigurationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super-admin', 'admin']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'brand_id' => ['nullable', 'string', 'max:255', 'required_if:enabled,true'],
            'api_key' => ['nullable', 'string', 'max:65535'],
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'enabled.required' => 'Please specify whether CHIP payments should be enabled.',
            'enabled.boolean' => 'The CHIP enabled value must be true or false.',
            'brand_id.required_if' => 'A CHIP Brand ID is required when enabling CHIP payments.',
            'brand_id.max' => 'The CHIP Brand ID may not be greater than 255 characters.',
            'api_key.max' => 'The CHIP API key is too long.',
        ];
    }
}
