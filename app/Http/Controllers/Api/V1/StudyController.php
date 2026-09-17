<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StudyState;
use App\Http\Resources\ApiShape;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Room;
use App\Models\ScreeningForm;
use App\Models\ScreeningQuestion;
use App\Models\Service;
use App\Models\StudyScreeningAnswer;
use App\Models\User;
use App\Models\Business;
use App\Models\UsageCounter;
use App\Services\EntitlementService;
use App\Services\StudyWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Radiology studies: booking, reception desk, technologist pipeline,
 * safety screening and radiologist assignment. All state changes go through
 * StudyWorkflowService (guarded + audited + transactional).
 */
class StudyController extends BaseApiController
{
    private StudyWorkflowService $workflow;

    public function __construct(StudyWorkflowService $workflow)
    {
        $this->workflow = $workflow;
    }

    // ==================== listing ====================

    public function index(Request $request): JsonResponse
    {
        $this->denyUnless('appointment manage');

        $studies = Appointment::forClinic($this->tenantId())
            ->with(self::eager())
            ->orderByRaw("CASE priority WHEN 'stat' THEN 0 WHEN 'urgent' THEN 1 ELSE 2 END")
            ->orderByDesc('date_sort')
            ->orderBy('time')
            ->get();

        return $this->ok(['studies' => $studies->map(fn ($a) => ApiShape::appointment($a))->all()]);
    }

    // ==================== booking ====================

    public function store(Request $request): JsonResponse
    {
        $this->denyUnless('appointment create');

        // Plan entitlement: monthly study volume is enforced server-side.
        if ($business = Business::find($this->tenantId())) {
            EntitlementService::enforce($business, 'studies', 'monthly study volume');
        }

        $validated = $request->validate([
            'patientId' => ['nullable', 'integer'],
            'newPatient' => ['nullable', 'array'],
            'newPatient.name' => ['required_with:newPatient', 'string', 'max:255'],
            'newPatient.phone' => ['nullable', 'string', 'max:40'],
            'newPatient.email' => ['nullable', 'email', 'max:255'],
            'newPatient.age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'newPatient.gender' => ['nullable', 'in:male,female,other'],
            'newPatient.bloodGroup' => ['nullable', 'string', 'max:8'],
            'newPatient.allergies' => ['nullable', 'string', 'max:2000'],
            'serviceId' => ['required', 'integer'],
            'referrerId' => ['nullable', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'string', 'max:20'],
            'priority' => ['required', 'in:routine,urgent,stat'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $service = Service::forClinic($this->tenantId())->with('modality')->findOrFail($validated['serviceId']);
        $modality = $service->modality;

        [$appointment, $invoice] = DB::transaction(function () use ($validated, $service, $modality) {
            // Resolve or create the patient (tenant-scoped).
            if (! empty($validated['patientId'])) {
                $customer = Customer::where('business_id', $this->tenantId())->findOrFail($validated['patientId']);
            } else {
                $np = $validated['newPatient'];
                $customer = Customer::create([
                    'name' => $np['name'],
                    'email' => $np['email'] ?? null,
                    'phone' => $np['phone'] ?? null,
                    'age' => $np['age'] ?? null,
                    'gender' => $np['gender'] ?? 'other',
                    'blood_group' => $np['bloodGroup'] ?? null,
                    'allergies' => $np['allergies'] ?? null,
                    'user_id' => $this->walkInUserId($np),
                    'business_id' => $this->tenantId(),
                    'created_by' => Auth::id(),
                ]);
            }

            $requiresScreening = $service->requires_screening || $service->contrast_type !== 'none';

            $room = Room::forClinic($this->tenantId())
                ->where('modality_id', $service->modality_id)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            $appointment = Appointment::create([
                'customer_id' => $customer->user_id,   // legacy convention: customers.user_id
                'name' => $customer->name,
                'email' => $customer->email,
                'contact' => $customer->phone,
                'service_id' => $service->id,
                'location_id' => $room?->location_id,
                'referrer_id' => $validated['referrerId'] ?? null,
                'date' => $validated['date'],
                'time' => $this->normalizeTime($validated['time']),
                'priority' => $validated['priority'],
                'workflow_state' => StudyState::Booked->value,
                'screening_required' => $requiresScreening,
                'screening_cleared' => false,
                'room_number' => $room?->name ?? 'Room 1',
                'notes' => $validated['notes'] ?? null,
                'business_id' => $this->tenantId(),
                'created_by' => Auth::id(),
            ]);

            // Token allocation is a domain rule: one allocator, race-free,
            // integer tokens (delegates to StudyTokenAllocator).
            \App\Services\StudyTokenAllocator::assignTo($appointment, $validated['date']);

            // Usage metering rides inside the same transaction as the study.
            UsageCounter::add($this->tenantId(), 'studies');

            // Domain fact: dispatched INSIDE the unit of work so its
            // notification row commits atomically with the booking (a crash
            // can no longer lose the clinical fact).
            event(new \App\Events\Study\StudyBooked($appointment, $service->name, $modality?->name));

            return [$appointment, $this->issueBookingInvoice($appointment, $service)];
        });

        $this->audit('appointment_created', $appointment, [
            'summary' => "Booked {$service->name} for {$appointment->patientDisplayName()} (token {$appointment->token_number}).",
        ]);

        // Fan the booking out to the tenant's interoperability integrations
        // (queued — HIS/RIS consumers hear about the study without adding
        // latency to the booking response).
        \App\Services\Delivery\IntegrationEventFanout::dispatch(
            $this->tenantId(),
            ['webhook', 'hl7', 'fhir'],
            'study.booked',
            [
                'studyId' => $appointment->id,
                'token' => $appointment->token_number,
                'patientName' => $appointment->patientDisplayName(),
                'study' => $service->name,
                'scheduledFor' => $validated['date'].' '.$this->normalizeTime($validated['time']),
                'bookedAt' => now()->toIso8601String(),
            ],
        );

        return response()->json([
            'data' => [
                'study' => ApiShape::appointment($appointment->fresh(self::eager())),
                'invoice' => ApiShape::invoice($invoice->fresh(['items', 'payments.receivedBy', 'appointment'])),
            ],
        ], 201);
    }

    // ==================== edit ====================

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        $this->denyUnless('appointment edit');

        if ($appointment->business_id !== $this->tenantId()) {
            abort(404);
        }

        $validated = $request->validate([
            'priority' => ['sometimes', 'in:routine,urgent,stat'],
            'roomNumber' => ['sometimes', 'nullable', 'string', 'max:80'],
            'time' => ['sometimes', 'string', 'max:20'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'assignedRadiologistId' => ['sometimes', 'nullable', 'integer'],
        ]);

        $updates = collect($validated)->only(['priority', 'roomNumber', 'time', 'notes'])
            ->mapWithKeys(fn ($v, $k) => [\Str::snake($k) => $v])
            ->all();

        if (isset($updates['time'])) {
            $updates['time'] = $this->normalizeTime($updates['time']);
        }

        if (array_key_exists('assignedRadiologistId', $validated)) {
            $this->denyUnless('study assign');
            $updates['assigned_radiologist_id'] = $validated['assignedRadiologistId'];
        }

        $appointment->fill($updates)->save();

        $this->audit('appointment_updated', $appointment, [
            'summary' => 'Updated study #'.$appointment->token_number.': '.implode(', ', array_keys($updates)),
        ]);

        return $this->ok(['study' => ApiShape::appointment($appointment->fresh(self::eager()))]);
    }

    // ==================== pipeline transitions ====================

    public function transition(Request $request, Appointment $appointment): JsonResponse
    {
        if ($appointment->business_id !== $this->tenantId()) {
            abort(404);
        }

        $action = $request->input('action');

        $permissionByAction = [
            'checkin' => 'study checkin',
            'no_show' => 'study checkin',
            'call' => 'study checkin',
            'prepare' => 'study acquire',
            'start' => 'study acquire',
            'complete' => 'study acquire',
            'send_to_reading' => 'study acquire',
            'cancel' => 'study cancel',
            'reject' => 'report edit',
        ];

        if (! isset($permissionByAction[$action])) {
            throw ValidationException::withMessages(['action' => 'Unknown workflow action.']);
        }

        $this->denyUnless($permissionByAction[$action]);

        switch ($action) {
            case 'checkin':
                // Notification is projected atomically inside the workflow
                // service (domain fact), not written here after commit.
                $this->workflow->checkIn($appointment);
                break;

            case 'no_show':
                $this->workflow->markNoShow($appointment);
                break;

            case 'call':
                // Queue-board call: persisted so every terminal (and the
                // audit trail) sees the same "now serving" state.
                $appointment->forceFill(['called_at' => now()])->save();
                $this->audit('queue_patient_called', $appointment, [
                    'summary' => "Called {$appointment->patientDisplayName()} (#{$appointment->token_number}) to ".($appointment->room_number ?: 'the examination area').'.',
                ]);
                break;

            case 'prepare':
                $this->workflow->startPreparing($appointment);
                break;

            case 'start':
                $this->workflow->startAcquisition($appointment);
                break;

            case 'complete':
                $validated = $request->validate([
                    'dose' => ['required', 'array'],
                    'dose.doseValue' => ['required', 'numeric', 'min:0'],
                    'dose.doseUnit' => ['required', 'string', 'max:30'],
                    'dose.dlpValue' => ['nullable', 'numeric'],
                    'dose.kvp' => ['nullable', 'numeric'],
                    'dose.mas' => ['nullable', 'numeric'],
                    'dose.sliceCount' => ['nullable', 'integer', 'min:0'],
                    'dose.seriesCount' => ['nullable', 'integer', 'min:0'],
                    'dose.contrastAgent' => ['nullable', 'string', 'max:255'],
                    'dose.contrastVolumeMl' => ['nullable', 'numeric', 'min:0'],
                    'dose.contrastFlowRate' => ['nullable', 'string', 'max:30'],
                    'dose.cannulaSite' => ['nullable', 'string', 'max:60'],
                    'dose.salineFlushMl' => ['nullable', 'numeric', 'min:0'],
                    'dose.techniqueNotes' => ['nullable', 'string', 'max:3000'],
                    'dose.qcPassed' => ['sometimes', 'boolean'],
                ]);
                $dose = collect($validated['dose'])
                    ->mapWithKeys(fn ($v, $k) => [\Str::snake($k) => $v])
                    ->all();

                $this->workflow->completeAcquisition($appointment, $dose, Auth::id());
                break;

            case 'send_to_reading':
                // PACS QC verified: hand the study to the reading radiologist.
                // (Notification projected atomically by the workflow service.)
                $this->workflow->sendToReading($appointment);
                break;

            case 'cancel':
            case 'reject':
                $reason = trim((string) $request->input('reason'));
                if ($reason === '') {
                    throw ValidationException::withMessages(['reason' => 'A reason is required.']);
                }
                if ($action === 'cancel') {
                    $this->workflow->cancel($appointment, $reason);
                } else {
                    $this->workflow->rejectToTechnologist($appointment, $reason);
                }
                break;
        }

        return $this->ok(['study' => ApiShape::appointment($appointment->fresh(self::eager()))]);
    }

    // ==================== safety screening ====================

    public function screeningForm(Request $request, Appointment $appointment): JsonResponse
    {
        $this->denyUnless('study screen');

        if ($appointment->business_id !== $this->tenantId()) {
            abort(404);
        }

        $service = $appointment->ServiceData;
        $form = ScreeningForm::forClinic($this->tenantId())
            ->where('is_active', true)
            ->where(function ($q) use ($service) {
                $q->where('modality_id', optional($service)->modality_id)->orWhereNull('modality_id');
            })
            ->orderByDesc('modality_id')
            ->with('questions')
            ->first();

        return $this->ok([
            'form' => $form ? ApiShape::screeningForm($form) : null,
            'answers' => $appointment->screeningAnswers()->with('question')->get()
                ->map(fn ($a) => ApiShape::studyScreeningAnswer($a))->all(),
        ]);
    }

    public function submitScreening(Request $request, Appointment $appointment): JsonResponse
    {
        $this->denyUnless('study screen');

        if ($appointment->business_id !== $this->tenantId()) {
            abort(404);
        }

        $validated = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.questionId' => ['required', 'integer'],
            'answers.*.answerValue' => ['required', 'string', 'max:255'],
            'answers.*.overrideReason' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($validated, $appointment) {
            $hasRisk = false;

            // Batch-load every requested question in ONE tenant-scoped query
            // (the per-answer ->find() was an N+1 on a safety-critical path).
            $questionIds = collect($validated['answers'])->pluck('questionId')->unique()->all();
            $questions = ScreeningQuestion::whereHas('form', fn ($q) => $q->where('business_id', $this->tenantId()))
                ->whereIn('id', $questionIds)
                ->get()
                ->keyBy('id');

            foreach ($validated['answers'] as $entry) {
                // Tenant-scoped and strict: an unknown question — or one from
                // ANOTHER clinic's form — fails the submission instead of being
                // silently skipped (skipping would clear the safety gate).
                $question = $questions->get($entry['questionId']);
                if (! $question) {
                    throw ValidationException::withMessages([
                        'answers' => 'Screening contains a question that does not belong to this clinic. Reload the form and try again.',
                    ]);
                }

                $isRisk = $question->flagsRisk($entry['answerValue']);
                $hasRisk = $hasRisk || $isRisk;

                StudyScreeningAnswer::updateOrCreate(
                    ['appointment_id' => $appointment->id, 'screening_question_id' => $question->id],
                    [
                        'answer_value' => $entry['answerValue'],
                        'is_risk' => $isRisk,
                        'override_reason' => $entry['overrideReason'] ?? null,
                        'answered_by' => Auth::id(),
                    ]
                );
            }

            $unresolvedRisk = $hasRisk && collect($validated['answers'])
                ->filter(fn ($a) => ($a['overrideReason'] ?? '') !== '')
                ->isEmpty();

            $appointment->forceFill([
                'screening_required' => true,
                'screening_cleared' => ! $hasRisk || ! $unresolvedRisk,
            ])->save();
        });

        $this->audit('screening_submitted', $appointment, [
            'summary' => "Safety screening recorded for #{$appointment->token_number} ({$appointment->patientDisplayName()}).",
        ]);

        return $this->ok(['study' => ApiShape::appointment($appointment->fresh(self::eager()))]);
    }

    // ==================== shared eager map ====================

    public static function eager(): array
    {
        return [
            'CustomerData', 'ServiceData.modality', 'referrer', 'assignedRadiologist',
            'performedBy.user', 'screeningAnswers.question', 'doseLog.recorder',
            'radiologyReports.releases.releaser',
        ];
    }

    // ==================== helpers ====================

    private function displayTime(?string $time): ?string
    {
        return $time ? ApiShape::time($time) : null;
    }

    private function normalizeTime(string $time): string
    {
        foreach (['h:i A', 'g:i A', 'H:i', 'H:i:s'] as $fmt) {
            try {
                $parsed = \Carbon\Carbon::createFromFormat($fmt, $time);
                if ($parsed !== false) {
                    return $parsed->format('H:i:s');
                }
            } catch (\Throwable) {
                continue;
            }
        }

        throw ValidationException::withMessages(['time' => 'Invalid time format.']);
    }

    /** Walk-in patients get a login-less user row (customers.user_id is required). */
    private function walkInUserId(array $np): int
    {
        $email = isset($np['email']) && $np['email'] !== ''
            ? $np['email']
            : 'walkin.'.time().'.'.random_int(100, 999).'@patients.local';

        // Email column is unique across the platform — never collide.
        if (User::where('email', $email)->exists()) {
            $email = 'patient.'.time().'.'.random_int(100, 999).'@patients.local';
        }

        $user = User::create([
            'name' => $np['name'],
            'email' => $email,
            'password' => Str::password(16),
            'type' => 'customer',
            'active_status' => 1,
            'is_enable_login' => 0,
            'business_id' => $this->tenantId(),
            'created_by' => Auth::id(),
        ]);

        return $user->id;
    }

    private function issueBookingInvoice(Appointment $appointment, Service $service): \App\Models\Invoice
    {
        $invoice = \App\Models\Invoice::create([
            'patient_id' => $appointment->customer_id,
            'appointment_id' => $appointment->id,
            'notes' => 'Initial booking study invoice',
            'issued_by' => Auth::id(),
            'issued_at' => now(),
            'business_id' => $this->tenantId(),
            'created_by' => Auth::id(),
        ]);

        $invoice->items()->create([
            'service_id' => $service->id,
            'description' => "{$service->name} ({$service->code})",
            'quantity' => 1,
            'unit_price' => (float) $service->price,
            'discount' => 0,
            'line_total' => (float) $service->price,
        ]);

        $invoice->recalculateTotals();

        return $invoice;
    }

}
