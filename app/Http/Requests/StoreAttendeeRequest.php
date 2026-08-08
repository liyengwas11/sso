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
        $attendeeId = $this->route('attendee')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'organisation' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:attendees,email,' . ($attendeeId ?? 'NULL')],
            'role_title' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'], // 5MB
            'days' => ['sometimes', 'array'],
            'days.*' => ['integer', 'min:1', 'exists:event_days,day_number'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.image' => 'The file must be an image.',
            'photo.mimes' => 'The photo must be a JPEG, PNG, or WebP file.',
            'photo.max' => 'The photo must not be larger than 5MB.',
            'email.unique' => 'This email is already registered as an attendee.',
            'days.*.exists' => 'One or more selected days are invalid.',
        ];
    }

    /**
     * Get the validated data with only the fields that exist on the attendee model
     */
    public function validatedAttendeeData(): array
    {
        return $this->safe()->only(['name', 'organisation', 'email', 'role_title']);
    }

    /**
     * Get the validated days array or null if not present
     */
    public function validatedDays(): ?array
    {
        return $this->filled('days') ? $this->input('days') : null;
    }

    /**
     * Check if a photo was uploaded
     */
    public function hasPhoto(): bool
    {
        return $this->hasFile('photo');
    }

    /**
     * Get the uploaded photo file
     */
    public function photo(): ?\Illuminate\Http\UploadedFile
    {
        return $this->file('photo');
    }
}
