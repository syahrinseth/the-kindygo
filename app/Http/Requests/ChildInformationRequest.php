<?php

namespace App\Http\Requests;

use App\Support\MalaysianIdentificationNumber;
use Illuminate\Foundation\Http\FormRequest;

class ChildInformationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $children = $this->input('children');

        if (! is_array($children)) {
            return;
        }

        foreach ($children as $index => $child) {
            if (is_array($child) && array_key_exists('mykid_no', $child)) {
                $children[$index]['mykid_no'] = MalaysianIdentificationNumber::format($child['mykid_no']);
            }
        }

        $this->merge(['children' => $children]);
    }

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
            'children.*.mykid_no' => ['nullable', 'string', 'regex:/^\d{6}-\d{2}-\d{4}$/'],
            'children.*.cert_number' => ['nullable', 'string', 'max:50'],
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
            'children.*.mykid_no.regex' => 'MyKid number must use the format 000000-00-0000.',
            'children.*.cert_number.max' => 'Certificate number must not exceed 50 characters.',
        ];
    }
}
