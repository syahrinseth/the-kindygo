<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ParentDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'occupation' => ['nullable', 'string', 'max:255'],

            // Residential Address
            'address' => ['required', 'string', 'max:500'],
            'address_2' => ['nullable', 'string', 'max:500'],
            'postal_code' => ['required', 'string', 'max:10'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:2'],

            // Office Information
            'office_address' => ['nullable', 'string', 'max:500'],
            'office_address_2' => ['nullable', 'string', 'max:500'],
            'office_postal_code' => ['nullable', 'string', 'max:10'],
            'office_city' => ['nullable', 'string', 'max:255'],
            'office_state' => ['nullable', 'string', 'max:2'],

            // Documents (Optional)
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'mykad_image' => ['nullable', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'immunization_card' => ['nullable', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],

            // Confirmation
            'information_confirmed' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'address.required' => 'Residential address is required.',
            'postal_code.required' => 'Postal code is required.',
            'city.required' => 'City is required.',
            'state.required' => 'State is required.',
            'profile_photo.image' => 'Profile photo must be an image.',
            'profile_photo.max' => 'Profile photo must not exceed 5MB.',
            'mykad_image.mimes' => 'MyKad image must be JPG, PNG, or PDF.',
            'mykad_image.max' => 'MyKad image must not exceed 10MB.',
            'immunization_card.mimes' => 'Immunization card must be JPG, PNG, or PDF.',
            'immunization_card.max' => 'Immunization card must not exceed 10MB.',
            'information_confirmed.required' => 'Please confirm that the information is accurate.',
            'information_confirmed.accepted' => 'Please confirm that the information is accurate.',
        ];
    }
}
