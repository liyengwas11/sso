<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // No session to check — Identity is verified inside
        // AttendanceService against user_id + employment_number, and
        // abuse is blunted by the throttle middleware on the route
        // plus the QR token's own short expiry.
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
