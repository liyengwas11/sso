<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    public function rules(): array
    {
        $employee = $this->route('employee');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'employment_number' => ['sometimes', 'string', 'max:50', Rule::unique('users', 'employment_number')->ignore($employee)],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee)],
            'timezone' => ['sometimes', 'string', 'max:50'],
            'status' => ['sometimes', Rule::in(['active', 'suspended'])],
        ];
    }
}
