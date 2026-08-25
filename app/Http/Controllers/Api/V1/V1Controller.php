<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\ServiceResource;
use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * API v1 — versioned, resource-based, clinic-scoped.
 * Legacy /api/* routes remain as shims (deprecation header).
 */
class V1Controller extends Controller
{
    private function clinicId(): int
    {
        return (int) (Auth::user()->active_business ?? getActiveBusiness());
    }

    private function ok($data, array $meta = []): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => $meta]);
    }

    // ---------- Auth ----------

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['error' => ['code' => 'invalid_credentials', 'message' => __('Invalid login details')]], 401);
        }

        $user = Auth::user();
        if (! in_array($user->type, ['admin', 'manager', 'staff'], true)) {
            Auth::logout();

            return response()->json(['error' => ['code' => 'forbidden', 'message' => __('Only clinic staff can use this API')]], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('v1-auth')->plainTextToken;

        return $this->ok([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'active_business' => $user->active_business,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->ok(['message' => __('Logged out')]);
    }

    // ---------- Reads ----------

    public function dashboard(Request $request): JsonResponse
    {
        $clinic = $this->clinicId();

        $stats = \Cache::remember("v1:dashboard:{$clinic}", 300, function () use ($clinic) {
            return [
                'total_appointment' => Appointment::forClinic($clinic)->count(),
                'total_revenue' => (float) \App\Models\AppointmentPayment::where('business_id', $clinic)->sum('amount'),
                'total_service' => Service::where('business_id', $clinic)->count(),
                'upcoming' => Appointment::forClinic($clinic)
                    ->with(Appointment::$eager)
                    ->whereDate('date_sort', '>=', now()->toDateString())
                    ->orderBy('date_sort')
                    ->orderBy('time')
                    ->take(10)
                    ->get(),
            ];
        });

        return $this->ok([
            'total_appointment' => $stats['total_appointment'],
            'total_revenue' => $stats['total_revenue'],
            'total_service' => $stats['total_service'],
            'business_url' => route('appointments.form', optional(\App\Models\Business::find($clinic))->slug),
            'upcoming_appointments' => AppointmentResource::collection($stats['upcoming']),
        ], ['cached_seconds' => 300]);
    }

    public function services(Request $request): JsonResponse
    {
        $services = Service::where('business_id', $this->clinicId())
            ->with('Category')
            ->when($request->category_id, fn ($q) => $q->where('category_id', $request->category_id))
            ->paginate(min((int) $request->per_page ?: 25, 100));

        return $this->ok(ServiceResource::collection($services), [
            'total' => $services->total(),
            'per_page' => $services->perPage(),
            'current_page' => $services->currentPage(),
            'last_page' => $services->lastPage(),
        ]);
    }

    public function appointments(Request $request): JsonResponse
    {
        $appointments = Appointment::forClinic($this->clinicId())
            ->with(Appointment::$eager)
            ->when($request->service_id, fn ($q) => $q->where('service_id', $request->service_id))
            ->when($request->status, fn ($q) => $q->where('appointment_status', $request->status))
            ->when($request->from && $request->to, fn ($q) => $q->whereBetween('date_sort', [$request->from, $request->to]))
            ->orderByDesc('date_sort')
            ->paginate(min((int) $request->per_page ?: 25, 100));

        return $this->ok(AppointmentResource::collection($appointments), [
            'total' => $appointments->total(),
            'per_page' => $appointments->perPage(),
            'current_page' => $appointments->currentPage(),
            'last_page' => $appointments->lastPage(),
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'date' => ['required', 'date_format:d-m-Y'],
            'staff_id' => ['nullable', 'integer'],
        ]);

        $slots = app(\App\Services\AvailabilityService::class)
            ->slots((int) $request->service_id, $request->date, $request->staff_id ? (int) $request->staff_id : null);

        return $this->ok(['date' => $request->date, 'slots' => $slots], ['cached_seconds' => 60]);
    }

    // ---------- Patient portal ----------

    public function myAppointments(Request $request): JsonResponse
    {
        $appointments = Appointment::where('customer_id', $request->user()->id)
            ->with(Appointment::$eager)
            ->orderByDesc('date_sort')
            ->paginate(min((int) $request->per_page ?: 15, 100));

        return $this->ok(AppointmentResource::collection($appointments), [
            'total' => $appointments->total(),
            'current_page' => $appointments->currentPage(),
            'last_page' => $appointments->lastPage(),
        ]);
    }

    public function cancelMyAppointment(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::where('customer_id', $request->user()->id)->find($id);

        if (! $appointment) {
            return response()->json(['error' => ['code' => 'not_found', 'message' => __('Appointment not found')]], 404);
        }

        // Policy: cancellations allowed until 24h before the slot.
        try {
            $slot = \Carbon\Carbon::createFromFormat('d-m-Y H:i', $appointment->date.' '.$appointment->time);
        } catch (Throwable) {
            $slot = null;
        }

        if ($slot && $slot->diffInHours(now(), false) > -24) {
            return response()->json(['error' => ['code' => 'too_late', 'message' => __('Appointments can only be cancelled up to 24 hours in advance')]], 422);
        }

        $cancelled = \App\Models\CustomStatus::where('title', 'Cancelled')->where('business_id', $appointment->business_id)->first();
        $old = $appointment->appointment_status;
        $appointment->appointment_status = $cancelled?->id ?? $appointment->appointment_status;
        $appointment->save();

        \App\Models\AuditLog::record('status_changed', $appointment, ['old' => $old, 'new' => $appointment->appointment_status]);

        return $this->ok(['message' => __('Appointment cancelled'), 'appointment' => new AppointmentResource($appointment->fresh())]);
    }
}
