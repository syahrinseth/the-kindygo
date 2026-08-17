<?php

namespace App\Http\Requests;

use App\Support\MyKadNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ParentBasicInfoRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'mykad_number' => MyKadNumber::format($this->input('mykad_number')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'mykad_number' => ['required', 'string', 'regex:/^\d{6}-\d{2}-\d{4}$/', Rule::unique('user_profiles', 'nric')->ignore($this->user()?->profile?->id)],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->user())],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'centre_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'centre_ids.*' => [
                'required',
                'integer',
                Rule::exists('centres', 'id')->where(function ($query) {
                    $tenant = $this->route('tenant');
                    if ($tenant) {
                        $query->where('tenant_id', $tenant->id);
                    }
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your full name as per MyKad.',
            'mykad_number.required' => 'MyKad number is required.',
            'mykad_number.regex' => 'MyKad number must use the format 000000-00-0000.',
            'mykad_number.unique' => 'This MyKad number is already registered.',
            'phone.required' => 'Phone number is required.',
            'email.required' => 'Email address is required.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'centre_ids.required' => 'Please select at least one centre.',
            'centre_ids.min' => 'Please select at least one centre.',
            'centre_ids.*.exists' => 'Selected centre is invalid.',
        ];
    }
}
