<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        // Base rules for all users
        $rules = [
            'name'           => ['nullable', 'string', 'max:255'],
            'last_name'      => ['nullable', 'string', 'max:255'],
            'first_name'     => ['nullable', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:10'],
            'email'          => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
            'birthday' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'phone'    => ['nullable', 'string', 'max:20', 'regex:/^([0-9\s\-\+\(\)]*)$/'],
            'address'  => ['nullable', 'string', 'max:500'],
        ];

        // Add student-specific fields
        if ($user->role === 'student' || $this->input('role') === 'student') {
            $rules['account_id'] = [
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'account_id')->ignore($user->id),
            ];

            // Course and Year Level are READ-ONLY for students
            // These fields are managed by administration only, not by the student
            // If submitted by a student, they are ignored (nullable, not required)
            $rules['course']     = ['nullable', 'string', 'max:255'];
            $rules['year_level'] = ['nullable', 'string', 'max:50', 'in:1st Year,2nd Year,3rd Year,4th Year'];

            // Only admin can change student status
            if (optional($this->user())->role === 'admin') {
                $rules['status'] = ['nullable', Rule::in(['active', 'graduated', 'dropped'])];
            }
        }

        // Add faculty field for accounting/admin users
        if (in_array($user->role, ['accounting', 'admin'])) {
            $rules['faculty'] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * Prepare the data for validation.
     * If only a `name` field is supplied (legacy tests / simple forms),
     * split it into first_name / last_name so the model is populated.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('name') && !$this->filled('first_name') && !$this->filled('last_name')) {
            $parts = explode(' ', trim($this->input('name')), 2);
            $this->merge([
                'first_name' => $parts[0] ?? '',
                'last_name'  => $parts[1] ?? $parts[0] ?? '',
            ]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'last_name'      => 'last name',
            'first_name'     => 'first name',
            'middle_initial' => 'middle initial',
            'account_id'     => 'account ID',
            'year_level'     => 'year level',
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
            'phone.regex'      => 'The phone number format is invalid.',
            'birthday.before'  => 'The birthday must be a date before today.',
            'email.unique'     => 'This email address is already in use.',
            'account_id.unique'=> 'This account ID is already in use.',
        ];
    }
}