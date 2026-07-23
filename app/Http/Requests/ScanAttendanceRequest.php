<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'size:48'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'employment_number' => ['required', 'string', 'max:50'],
        ];
    }
}
