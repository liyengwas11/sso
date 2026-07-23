<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-events') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'organisation' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'role_title' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'max:5120'], // 5MB
            // Null/absent = all days. Present = specific day numbers.
            'days' => ['sometimes', 'array'],
            'days.*' => ['integer', 'min:1'],
        ];
    }
}
