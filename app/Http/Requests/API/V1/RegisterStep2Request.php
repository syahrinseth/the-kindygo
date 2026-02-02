<?php

namespace App\Http\Requests\API\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for Step 2 of multi-step registration.
 *
 * Validates parent details including address, occupation, office information,
 * and required document uploads (profile photo, MyKad image, immunization card).
 */
class RegisterStep2Request extends FormRequest
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
            // Address information (required)
            'address' => ['required', 'string', 'max:500'],
            'address_2' => ['nullable', 'string', 'max:500'],
            'postal_code' => ['required', 'string', 'max:10'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:50'],

            // Occupation
            'occupation' => ['nullable', 'string', 'max:100'],

            // Office information (optional)
            'office_address' => ['nullable', 'string', 'max:500'],
            'office_address_2' => ['nullable', 'string', 'max:500'],
            'office_postal_code' => ['nullable', 'string', 'max:10'],
            'office_city' => ['nullable', 'string', 'max:100'],
            'office_state' => ['nullable', 'string', 'max:50'],

            // Required document uploads
            'profile_photo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'mykad_image' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:5120'],
            'immunization_card' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:5120'],
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
            'address.required' => 'Home address is required.',
            'postal_code.required' => 'Postal code is required.',
            'city.required' => 'City is required.',
            'state.required' => 'State is required.',
            'profile_photo.required' => 'Profile photo is required.',
            'profile_photo.image' => 'Profile photo must be an image.',
            'profile_photo.mimes' => 'Profile photo must be a JPEG, PNG, or WebP image.',
            'profile_photo.max' => 'Profile photo must not exceed 5MB.',
            'mykad_image.required' => 'MyKad image is required.',
            'mykad_image.mimes' => 'MyKad image must be a JPEG, PNG, WebP, or PDF file.',
            'mykad_image.max' => 'MyKad image must not exceed 5MB.',
            'immunization_card.required' => 'Immunization card is required.',
            'immunization_card.mimes' => 'Immunization card must be a JPEG, PNG, WebP, or PDF file.',
            'immunization_card.max' => 'Immunization card must not exceed 5MB.',
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
            'address' => 'home address',
            'address_2' => 'address line 2',
            'postal_code' => 'postal code',
            'office_address' => 'office address',
            'office_address_2' => 'office address line 2',
            'office_postal_code' => 'office postal code',
            'office_city' => 'office city',
            'office_state' => 'office state',
            'profile_photo' => 'profile photo',
            'mykad_image' => 'MyKad image',
            'immunization_card' => 'immunization card',
        ];
    }
}
