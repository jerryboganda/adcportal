<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'appointment_number' => AppointmentResource::number($this->resource),
            'date' => $this->date,
            'date_sort' => $this->date_sort?->format('Y-m-d'),
            'time' => $this->time,
            'customer' => $this->CustomerData?->name ?? $this->name ?? 'Guest',
            'email' => $this->CustomerData?->customer?->email ?? $this->email,
            'contact' => $this->CustomerData?->customer?->mobile_no ?? $this->contact,
            'staff' => $this->StaffData?->name ?? '-',
            'service' => $this->ServiceData?->name ?? '-',
            'location' => $this->LocationData?->name ?? '-',
            'payment_type' => $this->payment_type ?? '-',
            'status' => $this->StatusData?->title ?? 'Pending',
            'status_color' => $this->StatusData?->status_color ?? '5bc0de',
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** Appointment number without re-reading settings per row. */
    public static function number($appointment, ?array $settings = null): string
    {
        $settings ??= getCompanyAllSetting($appointment->created_by, $appointment->business_id);
        $prefix = $settings['appointment_prefix'] ?? '#APP0000';

        return $prefix.sprintf('%01d', $appointment->id);
    }
}
