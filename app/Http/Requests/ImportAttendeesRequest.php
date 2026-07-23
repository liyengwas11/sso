<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportAttendeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-events') ?? false;
    }

    public function rules(): array
    {
        return [
            // CSV only for Phase 1 — columns: name, organisation, email, role_title
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }
}
