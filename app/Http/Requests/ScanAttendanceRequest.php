<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated, active user with the scan permission may
        // scan. The QR token itself is validated in AttendanceService,
        // not here, since an expired/unknown token is a business-logic
        // failure (422) rather than a validation failure (422 too, but
        // with a friendlier custom message via the exception).
        return $this->user()?->can('scan-attendance') ?? false;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'size:48'],
        ];
    }
}
