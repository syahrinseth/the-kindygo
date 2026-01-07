<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChildInformationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'children' => ['nullable', 'array'],
            'children.*.first_name' => ['required', 'string', 'max:255'],
            'children.*.patronymic' => ['nullable', 'string', 'max:255'],
            'children.*.last_name' => ['required', 'string', 'max:255'],
            'children.*.gender' => ['required', 'string', 'in:male,female'],
            'children.*.date_of_birth' => ['required', 'date', 'before:today'],
            'children.*.place_of_birth' => ['nullable', 'string', 'max:255'],
            'children.*.race' => ['nullable', 'string', 'max:100'],
            'children.*.religion' => ['nullable', 'string', 'max:100'],
            'children.*.position_of_child' => ['nullable', 'integer', 'min:1'],
            'children.*.mykid_no' => ['required', 'string', 'max:12'],
            'children.*.cert_number' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'children.*.first_name.required' => 'Child\'s first name is required.',
            'children.*.patronymic.max' => 'Patronymic must not exceed 255 characters.',
            'children.*.last_name.required' => 'Child\'s last name is required.',
            'children.*.date_of_birth.required' => 'Child\'s date of birth is required.',
            'children.*.date_of_birth.date' => 'Please enter a valid date.',
            'children.*.date_of_birth.before' => 'Date of birth must be in the past.',
            'children.*.gender.required' => 'Child\'s gender is required.',
            'children.*.gender.in' => 'Please select a valid gender.',
            'children.*.place_of_birth.max' => 'Place of birth must not exceed 255 characters.',
            'children.*.race.max' => 'Race must not exceed 100 characters.',
            'children.*.religion.max' => 'Religion must not exceed 100 characters.',
            'children.*.position_of_child.integer' => 'Position of child must be a number.',
            'children.*.position_of_child.min' => 'Position of child must be at least 1.',
            'children.*.mykid_no.max' => 'MyKID number must not exceed 12 characters.',
            'children.*.cert_number.max' => 'Certificate number must not exceed 50 characters.',
        ];
    }
}
