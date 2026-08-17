<?php

namespace App\Http\Requests\API\V1;

use App\Support\MalaysianIdentificationNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for Step 3 of multi-step registration.
 *
 * Validates children data. This step is optional and can be skipped entirely
 * by passing an empty array or omitting the children field.
 */
class RegisterStep3Request extends FormRequest
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
            // Children array is optional (can be empty to skip this step)
            'children' => ['nullable', 'array'],

            // Child details (required when children are provided)
            'children.*.first_name' => ['required_with:children', 'string', 'max:100'],
            'children.*.patronymic' => ['nullable', 'string', 'max:100'],
            'children.*.last_name' => ['nullable', 'string', 'max:100'],
            'children.*.date_of_birth' => ['required_with:children', 'date', 'before:today'],
            'children.*.gender' => ['required_with:children', 'string', 'in:male,female'],
            'children.*.place_of_birth' => ['nullable', 'string', 'max:100'],
            'children.*.race' => ['nullable', 'string', 'max:50'],
            'children.*.religion' => ['nullable', 'string', 'max:50'],
            'children.*.position_of_child' => ['nullable', 'integer', 'min:1'],
            'children.*.mykid_no' => ['nullable', 'string', 'regex:/^\d{6}-\d{2}-\d{4}$/'],
            'children.*.cert_number' => ['nullable', 'string', 'max:50'],
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
            'children.*.first_name.required_with' => 'Child\'s first name is required.',
            'children.*.first_name.max' => 'Child\'s first name must not exceed 100 characters.',
            'children.*.date_of_birth.required_with' => 'Child\'s date of birth is required.',
            'children.*.date_of_birth.date' => 'Child\'s date of birth must be a valid date.',
            'children.*.date_of_birth.before' => 'Child\'s date of birth must be in the past.',
            'children.*.gender.required_with' => 'Child\'s gender is required.',
            'children.*.gender.in' => 'Child\'s gender must be male or female.',
            'children.*.mykid_no.regex' => 'MyKid number must use the format 000000-00-0000.',
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
            'children.*.first_name' => 'child\'s first name',
            'children.*.patronymic' => 'child\'s patronymic',
            'children.*.last_name' => 'child\'s last name',
            'children.*.date_of_birth' => 'child\'s date of birth',
            'children.*.gender' => 'child\'s gender',
            'children.*.place_of_birth' => 'child\'s place of birth',
            'children.*.race' => 'child\'s race',
            'children.*.religion' => 'child\'s religion',
            'children.*.position_of_child' => 'child\'s position in family',
            'children.*.mykid_no' => 'child\'s MyKID number',
            'children.*.cert_number' => 'child\'s birth certificate number',
        ];
    }
}
