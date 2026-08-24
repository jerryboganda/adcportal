<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the public booking form (web + API).
 * Replaces the inline if-checks in the legacy controller.
 */
class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public endpoint
    }

    public function rules(): array
    {
        return [
            'business_id' => ['required', 'integer', 'exists:businesses,id'],
            'service' => ['required', 'integer', 'exists:services,id'],
            'staff' => ['required', 'integer'],
            'location' => ['nullable', 'integer'],
            'appointment_date' => ['required', 'date_format:d-m-Y'],
            'duration' => ['required', 'date_format:H:i'],
            'type' => ['required', 'in:new-user,existing-user,guest-user'],
            'name' => ['required_unless:type,existing-user', 'nullable', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            'contact' => [
                'required',
                'string',
                'max:20',
                'regex:/^\+[1-9]\d{0,2}\d{6,14}$/',
            ],
            'password' => ['nullable', 'string', 'min:6', 'max:64'],
            'customer' => ['nullable', 'integer'],
            'payment' => ['required', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'referred_by' => ['nullable', 'string', 'max:191'],
            'gender' => ['nullable', 'in:male,female,other'],
            'dob' => ['nullable', 'date', 'before:today'],
            'description' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:204800'],
            'values' => ['nullable', 'array'],
            'values.*' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact.regex' => __('Please add mobile number with country code. (ex. +92)'),
            'duration.date_format' => __('Please select a valid time slot.'),
            'appointment_date.date_format' => __('Please select a valid date.'),
        ];
    }

    /**
     * Let the BookingService work with the same flat array shape the legacy
     * controller used (attachment as UploadedFile, values as nested array).
     */
    public function bookingData(): array
    {
        return array_merge($this->validated(), [
            'attachment' => $this->file('attachment'),
        ]);
    }
}
