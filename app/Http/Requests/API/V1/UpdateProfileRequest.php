<?php

namespace App\Http\Requests\API\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],

            // Profile fields
            'profile' => ['sometimes', 'array'],
            'profile.phone' => ['nullable', 'string', 'max:20'],
            'profile.nric' => ['nullable', 'string', 'max:14'],
            'profile.passport' => ['nullable', 'string', 'max:50'],
            'profile.gender' => ['nullable', 'string', 'in:male,female'],
            'profile.date_of_birth' => ['nullable', 'date', 'before:today'],
            'profile.nationality' => ['nullable', 'string', 'max:100'],
            'profile.occupation' => ['nullable', 'string', 'max:255'],
            'profile.relationship_to_child' => ['nullable', 'string', 'max:100'],
            'profile.tin' => ['nullable', 'string', 'max:50'],

            // Address fields
            'address' => ['sometimes', 'array'],
            'address.address' => ['nullable', 'string', 'max:500'],
            'address.address_2' => ['nullable', 'string', 'max:500'],
            'address.city' => ['nullable', 'string', 'max:100'],
            'address.postal_code' => ['nullable', 'string', 'max:10'],
            'address.state_code' => ['nullable', 'string', 'max:10'],
            'address.country' => ['nullable', 'string', 'max:100'],
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
            'name.max' => 'Name cannot exceed 255 characters.',
            'profile.gender.in' => 'Gender must be either male or female.',
            'profile.date_of_birth.before' => 'Date of birth must be in the past.',
            'profile.nric.max' => 'NRIC cannot exceed 14 characters.',
        ];
    }
}
