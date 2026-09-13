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

        [$appointment, $invoice] = DB::transaction(function () use ($validated, $service) {
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
                'customer_id' => $customer->id,
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

            $appointment->forceFill(['token_number' => $this->nextToken($service->modality?->code ?? 'ST', $validated['date'])])->save();

            return [$appointment, $this->issueBookingInvoice($appointment, $service)];
        });

        if ($appointment->priority === 'stat') {
            $this->notify([
                'title' => "🚨 STAT Booking Created (#{$appointment->token_number})",
                'message' => "Emergency priority study scheduled for {$appointment->patientDisplayName()} ({$service->name}). Modality: {$modality?->name}.",
                'category' => 'stat',
                'priority' => 'critical',
                'appointment_id' => $appointment->id,
                'token_number' => $appointment->token_number,
                'patient_name' => $appointment->patientDisplayName(),
                'target_tab' => 'technologist',
                'action_label' => 'Open Tech Worklist',
            ]);
        } else {
            $this->notify([
                'title' => "New Appointment Booked (#{$appointment->token_number})",
                'message' => $appointment->patientDisplayName().' registered for '.$service->name.' at '.$this->displayTime($appointment->time).'.',
                'category' => 'workflow',
                'priority' => 'low',
                'appointment_id' => $appointment->id,
                'token_number' => $appointment->token_number,
                'patient_name' => $appointment->patientDisplayName(),
                'target_tab' => 'checkin',
                'action_label' => 'View Reception Desk',
            ]);
        }

        $this->audit('appointment_created', $appointment, [
            'summary' => "Booked {$service->name} for {$appointment->patientDisplayName()} (token {$appointment->token_number}).",
        ]);

        return response()->json([
            'data' => [
                'study' => ApiShape::appointment($appointment->fresh(self::eager())),
                'invoice' => ApiShape::invoice($invoice->fresh(['items', 'payments.receiver', 'appointment'])),
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
            'prepare' => 'study acquire',
            'start' => 'study acquire',
            'complete' => 'study acquire',
            'cancel' => 'study cancel',
            'reject' => 'report edit',
        ];

        if (! isset($permissionByAction[$action])) {
            throw ValidationException::withMessages(['action' => 'Unknown workflow action.']);
        }

        $this->denyUnless($permissionByAction[$action]);

        switch ($action) {
            case 'checkin':
                $this->workflow->checkIn($appointment);
                $this->notifyCheckIn($appointment);
                break;

            case 'no_show':
                $this->workflow->markNoShow($appointment);
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
                $this->notifyAcquisition($appointment);
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
                    $this->notifyRejection($appointment, $reason);
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

            foreach ($validated['answers'] as $entry) {
                $question = ScreeningQuestion::find($entry['questionId']);
                if (! $question) {
                    continue;
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

    private function nextToken(string $modalityCode, string $date): string
    {
        $prefix = $modalityCode.'-';
        $existing = Appointment::forClinic($this->tenantId())
            ->whereDate('date_sort', $date)
            ->where('token_number', 'like', $prefix.'%')
            ->pluck('token_number');

        $max = $existing
            ->map(fn ($t) => (int) substr((string) $t, strlen($prefix)))
            ->filter(fn ($n) => $n > 0)
            ->max() ?? 0;

        return $prefix.str_pad((string) ($max + 1), 2, '0', STR_PAD_LEFT);
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

        $this->recalculate($invoice);

        return $invoice;
    }

    private function recalculate(\App\Models\Invoice $invoice): void
    {
        $invoice->refresh();
        $subtotal = (float) $invoice->items()->sum('line_total');
        $discount = (float) $invoice->items()->sum('discount');
        $taxable = max(0, $subtotal - $discount);
        $taxAmount = round($taxable * ((float) $invoice->tax_rate / 100), 2);

        $invoice->forceFill([
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'tax_amount' => $taxAmount,
            'total' => round($taxable + $taxAmount, 2),
        ])->save();
    }

    private function notifyCheckIn(Appointment $appointment): void
    {
        $this->notify([
            'title' => "Patient Checked In (#{$appointment->token_number})",
            'message' => "{$appointment->patientDisplayName()} has arrived at the reception desk. Token {$appointment->token_number} is now ready for preparation in {$appointment->ServiceData?->modality?->name}.",
            'category' => 'workflow',
            'priority' => $appointment->priority === 'stat' ? 'critical' : 'medium',
            'appointment_id' => $appointment->id,
            'token_number' => $appointment->token_number,
            'patient_name' => $appointment->patientDisplayName(),
            'target_tab' => 'technologist',
            'action_label' => 'View Worklist',
        ]);
    }

    private function notifyAcquisition(Appointment $appointment): void
    {
        $this->notify([
            'title' => "Acquisition Complete (#{$appointment->token_number})",
            'message' => ($appointment->ServiceData?->modality?->code ?? 'Imaging')." imaging completed for {$appointment->patientDisplayName()}. Study ready for reporting.",
            'category' => 'workflow',
            'priority' => $appointment->priority === 'stat' ? 'high' : 'medium',
            'appointment_id' => $appointment->id,
            'token_number' => $appointment->token_number,
            'patient_name' => $appointment->patientDisplayName(),
            'target_tab' => 'reporting',
            'action_label' => 'Open Diagnostic Report',
        ]);
    }

    private function notifyRejection(Appointment $appointment, string $reason): void
    {
        $this->notify([
            'title' => "Quality Rejection Alert (#{$appointment->token_number})",
            'message' => Auth::user()->name." rejected study #{$appointment->token_number} back for repeat scan/technologist review: \"{$reason}\"",
            'category' => 'stat',
            'priority' => 'high',
            'appointment_id' => $appointment->id,
            'token_number' => $appointment->token_number,
            'patient_name' => $appointment->patientDisplayName(),
            'target_tab' => 'technologist',
            'action_label' => 'View Study in Worklist',
        ]);
    }
}
