<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\Business;
use App\Models\Customer;
use App\Models\ContactUs;
use App\Models\Service;
use App\Events\CreateAppoinment;
use App\Events\AppointmentPaymentData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Atomic booking pipeline.
 *
 * Guarantees:
 *  - No double-booking: per-slot cache lock + in-lock availability re-check.
 *  - Appointment + payment created in one transaction.
 *  - Response contract identical to the legacy controller (status/message/url/...).
 */
class BookingService
{
    public function book(Business $business, Service $service, array $data): array
    {
        $slotKey = sprintf(
            'booking:%d:%s:%s:%s',
            $business->id,
            $data['staff'] ?? 'any',
            $data['appointment_date'],
            md5((string) ($data['duration'] ?? ''))
        );

        $lock = Cache::lock($slotKey, 10);

        try {
            // Block until we own the slot (3s max) — parallel submissions serialize.
            $lock->block(3);
        } catch (Illuminate\Contracts\Cache\LockTimeoutException) {
            return $this->respond($business, 'failed', __('This time slot is being booked by someone else. Please pick another slot.'), 'failed');
        }

        try {
            // In-lock re-check: the cached slot list may be up to 60s stale.
            if (! empty($data['duration']) && $this->slotTaken($business, $service, $data)) {
                return $this->respond($business, 'failed', __('This time slot has just been booked. Please pick another slot.'), 'failed');
            }

            $appointment = DB::transaction(function () use ($business, $service, $data, &$paymentUrl) {
                $appointment = $this->createAppointment($business, $service, $data);
                $this->createPayment($business, $service, $appointment, $data);

                return $appointment;
            });

            \App\Models\AuditLog::record('created', $appointment, ['via' => 'public_form']);
            event(new CreateAppoinment($appointment, new \Illuminate\Http\Request($data)));

            // Invalidate the cached slot list for this date immediately.
            AvailabilityService::forget($service->id, $data['staff'] ?? null, $data['appointment_date']);

            $this->notify($business, $appointment, $data);

            return $this->respond(
                $business,
                'success',
                __('The Payment has been added successfully.'),
                $appointment->id,
                $appointment
            );
        } catch (Throwable $e) {
            Log::error('Booking failed: '.$e->getMessage(), ['exception' => $e]);

            return $this->respond($business, 'error', __('Failed to create appointment.'), null, null, 'There was an error processing your booking. Please try again.');
        } finally {
            optional($lock)->release();
        }
    }

    private function slotTaken(Business $business, Service $service, array $data): bool
    {
        $slots = app(AvailabilityService::class)->slots($service->id, $data['appointment_date'], $data['staff'] ?? null);

        foreach ($slots as $slot) {
            if ($slot['start'] === $data['duration']) {
                return false; // still available
            }
        }

        return true;
    }

    private function createAppointment(Business $business, Service $service, array $data): Appointment
    {
        $type = $data['type'] ?? null;
        $customer = null;

        if ($type === 'guest-user') {
            $this->recordGuestContact($business, $data);
        }

        if ($type === 'new-user') {
            $customer = $this->createCustomerWithUser($business, $data);
        }

        if ($type === 'existing-user') {
            $customer = $this->resolveExistingCustomer($data);
        }

        $defaultStatus = company_setting('default_status', $business->created_by, $business->id);

        $appointment = new Appointment;
        $appointment->customer_id = match ($type) {
            'new-user', 'existing-user' => $customer?->user_id,
            'guest-user' => null,
            default => $data['customer'] ?? null,
        };
        $appointment->location_id = $data['location'] ?? null;
        $appointment->service_id = $service->id;
        $appointment->staff_id = $data['staff'] ?? null;

        if ($type === 'guest-user') {
            $appointment->name = $data['name'] ?? null;
            $appointment->email = $data['email'] ?? null;
            $appointment->contact = $data['contact'] ?? null;
        }

        $appointment->date = $data['appointment_date'] ?? '';
        $appointment->time = $data['duration'] ?? '';
        $appointment->notes = $data['notes'] ?? '';
        $appointment->referred_by = $data['referred_by'] ?? null;
        $appointment->payment_type = $data['payment'] ?? 'Manually';
        $appointment->appointment_status = ! empty($defaultStatus) ? $defaultStatus : 'Pending';
        $appointment->attachment = $this->storeAttachment($data) ?? null;
        $appointment->custom_field = $this->encodeCustomFields($business, $data);
        $appointment->business_id = $business->id;
        $appointment->created_by = $business->created_by;
        $appointment->save();

        return $appointment;
    }

    private function createPayment(Business $business, Service $service, Appointment $appointment, array $data): AppointmentPayment
    {
        $payment = AppointmentPayment::create([
            'appointment_id' => $appointment->id,
            'payment_type' => $appointment->payment_type,
            'amount' => $service->price,
            'payment_date' => now(),
            'business_id' => $business->id,
            'created_by' => $business->created_by,
        ]);

        event(new AppointmentPaymentData($data, $payment, $service));

        return $payment;
    }

    private function recordGuestContact(Business $business, array $data): void
    {
        $exists = ContactUs::where('email', $data['email'] ?? '')
            ->where('business_id', $business->id)
            ->exists();

        if (! $exists) {
            ContactUs::create([
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'contact' => $data['contact'] ?? null,
                'subject' => 'Appointment Booking - Guest',
                'description' => 'Contact created from appointment booking',
                'theme' => 'default',
                'business_id' => $business->id,
            ]);
        }
    }

    private function createCustomerWithUser(Business $business, array $data): ?Customer
    {
        $role = \App\Models\Role::where('name', 'customer')->where('created_by', $business->created_by)->first();
        if (! $role) {
            return null;
        }

        $user = \App\Models\User::create([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'mobile_no' => $data['contact'] ?? null,
            'email_verified_at' => now(),
            'password' => ! empty($data['password']) ? Hash::make($data['password']) : null,
            'avatar' => 'uploads/users-avatar/avatar.png',
            'type' => 'customer',
            'lang' => 'en',
            'business_id' => $business->id,
            'created_by' => $business->created_by,
        ]);
        $user->addRole($role);

        $customer = new Customer;
        $customer->name = $data['name'] ?? null;
        $customer->user_id = $user->id;
        $customer->gender = $data['gender'] ?? '';
        $customer->dob = $data['dob'] ?? '';
        $customer->description = $data['description'] ?? '';
        $customer->business_id = $user->business_id;
        $customer->created_by = $user->created_by;
        $customer->save();

        return $customer;
    }

    private function resolveExistingCustomer(array $data): ?Customer
    {
        $user = \App\Models\User::where('email', $data['email'] ?? '')->where('type', 'customer')->first();

        if (! $user || empty($data['password']) || ! Hash::check($data['password'], $user->password)) {
            abort(response()->json([
                'status' => 'error',
                'message' => __('Authentication failed.'),
                'error' => empty($data['password']) ? __('Please enter valid email') : __('Enter correct password'),
            ], 422));
        }

        return Customer::where('user_id', $user->id)->first();
    }

    private function storeAttachment(array $data): ?string
    {
        if (empty($data['attachment']) || ! ($data['attachment'] instanceof \Illuminate\Http\UploadedFile)) {
            return null;
        }

        $file = $data['attachment'];
        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).'_'.time().'.'.$file->getClientOriginalExtension();

        // Reuse the app's upload helper (respects storage settings: local/S3).
        $request = new \Illuminate\Http\Request;
        $request->files->set('attachment', $file);
        $upload = upload_file($request, 'attachment', $filename, 'Appointment');

        return ($upload['flag'] ?? 0) == 1 ? $upload['url'] : null;
    }

    private function encodeCustomFields(Business $business, array $data): ?string
    {
        $enabled = company_setting('custom_field_enable', $business->created_by, $business->id);
        if (empty($enabled) || $enabled !== 'on' || empty($data['values'])) {
            return null;
        }

        $out = [];
        foreach ($data['values'] as $type => $fields) {
            foreach ($fields as $label => $value) {
                if (is_array($value)) {
                    $out[$label] = $type === 'checkbox' ? implode(',', $value) : json_encode($value);
                } else {
                    $out[$label] = $value;
                }
            }
        }

        return $out ? json_encode($out) : null;
    }

    private function notify(Business $business, Appointment $appointment, array $data): void
    {
        $settings = getCompanyAllSetting($appointment->created_by, $appointment->business_id);

        if (empty($settings['Create Appointment']) || $settings['Create Appointment'] != true) {
            return;
        }

        $appointment->loadMissing(['ServiceData', 'LocationData', 'StaffData.user', 'CustomerData.customer']);

        $uArr = [
            'company_name' => $business->name ?? '',
            'service' => $appointment->ServiceData?->name ?? '-',
            'location' => $appointment->LocationData?->name ?? '-',
            'staff' => $appointment->StaffData?->user?->name ?? '-',
            'appointment_date' => $appointment->date,
            'appointment_time' => $appointment->time,
            'appointment_number' => Appointment::appointmentNumberWithFormat($appointment->id, $settings),
            'tracking_url' => route('find.appointment', ['businessSlug' => $business->slug]),
        ];

        $email = $appointment->CustomerData?->customer?->email ?? $appointment->email;

        \App\Models\EmailTemplate::sendEmailTemplate('Create Appointment', [$email], $uArr, $appointment->created_by, $business->id);
    }

    private function respond(Business $business, string $status, string $message, $appointmentId = null, ?Appointment $appointment = null, ?string $error = null): array
    {
        $url = route('appointments.form', ['slug' => $business->slug, 'appointment' => $appointmentId ?? 'failed']);

        $payload = ['status' => $status, 'message' => $message, 'url' => $url];

        if ($status === 'success' && $appointment) {
            $payload['appointment_id'] = $appointment->id;
            $payload['appointment_number'] = Appointment::appointmentNumberWithFormat(
                $appointment->id,
                getCompanyAllSetting($appointment->created_by, $appointment->business_id)
            );
        }

        if ($error) {
            $payload['error'] = $error;
        }

        return $payload;
    }
}
