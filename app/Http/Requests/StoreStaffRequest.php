<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('staffMember')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . ($userId ?? 'NULL')],
            'password' => [$userId ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.max' => 'The photo must not be larger than 2MB.',
            'photo.mimes' => 'The photo must be a JPEG, PNG, GIF, or WebP file.',
            'email.unique' => 'This email is already registered as a staff member.',
        ];
    }
}
