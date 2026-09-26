<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StudyState;
use App\Http\Resources\ApiShape;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Room;
use App\Models\ScreeningForm;
use App\Models\ScreeningQuestion;
use App\Models\Service;
use App\Models\StudyScreeningAnswer;
use App\Models\User;
use App\Models\Business;
use App\Models\UsageCounter;
use App\Services\EntitlementService;
use App\Services\ScreeningTriageService;
use App\Services\StudyWorkflowService;
use App\Support\BookingMoney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Radiology studies: booking, reception desk, technologist pipeline,
 * safety screening and radiologist assignment. All state changes go through
 * StudyWorkflowService (guarded + audited + transactional).
 */
class StudyController extends BaseApiController
{
    private StudyWorkflowService $workflow;
    private ScreeningTriageService $triage;

    public function __construct(StudyWorkflowService $workflow, ScreeningTriageService $triage)
    {
        $this->workflow = $workflow;
        $this->triage = $triage;
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
            'roomId' => ['nullable', 'integer'],
            // Tenant-scoped like every other foreign key in this payload. As a
            // bare `integer` this stored another clinic's referrer id, and
            // `Appointment::referrer()` + `ApiShape::referrer()` then handed back
            // that doctor's name, clinic, email and phone to whoever booked.
            'referrerId' => [
                'nullable',
                'integer',
                Rule::exists('referrers', 'id')->where(fn ($q) => $q->where('business_id', $this->tenantId())),
            ],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'string', 'max:20'],
            'priority' => ['required', 'in:routine,urgent,stat'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Booking-time financials: settled server-side against the
            // auto-issued invoice — the client never dictates totals.
            'discount' => ['nullable', 'array'],
            'discount.amount' => ['required_with:discount', 'numeric', 'min:0'],
            'payment' => ['nullable', 'array'],
            'payment.status' => ['required_with:payment', 'in:unpaid,partial,paid'],
            'payment.amountPaid' => ['nullable', 'numeric', 'min:0'],
            'payment.method' => ['nullable', 'integer', 'min:1'],
            'payment.reference' => ['nullable', 'string', 'max:255'],
        ]);

        // Layered RBAC: booking needs `appointment create`; touching the
        // booking invoice's money needs the matching billing permission.
        $wantsPayment = isset($validated['payment'])
            && in_array($validated['payment']['status'], ['partial', 'paid'], true);
        $wantsDiscount = isset($validated['discount'])
            && (float) ($validated['discount']['amount'] ?? 0) > 0;

        if ($wantsPayment) {
            $this->denyUnless('invoice payment');
        }
        if ($wantsDiscount) {
            $this->denyUnless('invoice edit');
        }

        if (isset($validated['payment']) && $validated['payment']['status'] === 'unpaid'
            && (float) ($validated['payment']['amountPaid'] ?? 0) > 0) {
            throw ValidationException::withMessages([
                'payment.amountPaid' => 'An unpaid booking cannot record an amount received.',
            ]);
        }

        $service = Service::forClinic($this->tenantId())->with('modality')->findOrFail($validated['serviceId']);
        $modality = $service->modality;

        // Authoritative money: the TENANT'S configured price for THIS
        // procedure, read server-side. A client that posts its own price or
        // payable total is ignored entirely.
        $basePrice = BookingMoney::fromMinor(BookingMoney::toMinor($service->price));
        $discountAmount = $wantsDiscount
            ? BookingMoney::fromMinor(BookingMoney::toMinor($validated['discount']['amount']))
            : 0.0;

        if ($wantsDiscount) {
            $discountError = BookingMoney::validateDiscount($basePrice, $discountAmount);

            if ($discountError !== null) {
                // Rejected, never silently clamped: billing a different amount
                // than the one entered is worse than refusing the booking.
                throw ValidationException::withMessages([
                    'discount.amount' => $discountError === BookingMoney::ERROR_DISCOUNT_EXCEEDS_PRICE
                        ? $discountError.' (Rs. '.BookingMoney::format($basePrice).').'
                        : $discountError,
                ]);
            }
        }

        // Imaging suite: explicitly chosen (validated against tenant, activity
        // and modality) or auto-assigned as before. No suite configured → the
        // study simply has no room yet; the SPA shows the honest empty state.
        $room = null;
        if (! empty($validated['roomId'])) {
            $room = Room::forClinic($this->tenantId())->findOrFail($validated['roomId']);

            if (! $room->is_active) {
                throw ValidationException::withMessages(['roomId' => 'The selected modality suite is inactive.']);
            }
            if ((int) $room->modality_id !== (int) $service->modality_id) {
                throw ValidationException::withMessages(['roomId' => 'The selected suite does not belong to the selected modality.']);
            }
        } else {
            $room = Room::forClinic($this->tenantId())
                ->where('modality_id', $service->modality_id)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();
        }

        [$appointment, $invoice, $paymentSummary] = DB::transaction(function () use ($validated, $service, $modality, $room, $wantsPayment, $discountAmount, $basePrice) {
            // Resolve or create the patient (tenant-scoped).
            if (! empty($validated['patientId'])) {
                $customer = Customer::where('business_id', $this->tenantId())->findOrFail($validated['patientId']);
            } else {
                // One registration path for every entry point (booking,
                // reception, reporting): a customer user identity plus the
                // clinical record, never a bare Customer row.
                $customer = Customer::register($validated['newPatient'], $this->tenantId(), Auth::id());
            }

            $requiresScreening = $service->requires_screening || $service->contrast_type !== 'none';

            $appointment = Appointment::create([
                'customer_id' => $customer->user_id,   // legacy convention: customers.user_id
                'name' => $customer->name,
                'email' => $customer->email,
                'contact' => $customer->phone,
                'service_id' => $service->id,
                'location_id' => $room?->location_id,
                'room_id' => $room?->id,
                'room_number' => $room?->name,
                'referrer_id' => $validated['referrerId'] ?? null,
                'date' => $validated['date'],
                'time' => $this->normalizeTime($validated['time']),
                'priority' => $validated['priority'],
                'workflow_state' => StudyState::Booked->value,
                'screening_required' => $requiresScreening,
                'screening_cleared' => false,
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

            $invoice = $this->issueBookingInvoice($appointment, $service);

            // Booking-time cash discount — recorded on the invoice, and
            // re-derived into its totals by the model (never taken from the
            // request). A 100% discount is legal and yields payable 0.
            if ($discountAmount > 0) {
                $invoice->forceFill(['manual_discount' => $discountAmount])->save();
                $invoice->recalculateTotals();

                // The invoice's own math is authoritative: if it cannot
                // represent the discount exactly (a future line item or tax
                // change), fail the booking instead of quietly billing
                // something else.
                if (! BookingMoney::equals($invoice->discount_total, $discountAmount)) {
                    throw ValidationException::withMessages([
                        'discount.amount' => BookingMoney::ERROR_DISCOUNT_EXCEEDS_PRICE
                            .' (Rs. '.BookingMoney::format($basePrice).').',
                    ]);
                }
            }

            // Settle against the freshly issued booking invoice. All money
            // math (payable total, balance) is server-computed from the
            // tenant's configured service price — never from the client.
            $paymentSummary = null;
            if ($wantsPayment) {
                $paymentSummary = $this->settleBookingPayment($invoice, $validated['payment']);
            }

            return [$appointment, $invoice, $paymentSummary];
        });

        $this->audit('appointment_created', $appointment, [
            'summary' => "Booked {$service->name} for {$appointment->patientDisplayName()} (token {$appointment->token_number}).",
        ]);

        // Discount trail: who discounted what, against which price and tenant
        // (AuditLog::record stamps the actor, ip and business_id).
        if ($discountAmount > 0) {
            $this->audit('booking_discount_applied', $invoice, [
                'summary' => sprintf(
                    'Applied Rs. %s discount to %s (token %s): price Rs. %s → payable Rs. %s.',
                    BookingMoney::format($discountAmount),
                    $invoice->invoice_number,
                    $appointment->token_number,
                    BookingMoney::format($basePrice),
                    BookingMoney::format($invoice->total),
                ),
                'bookingId' => $appointment->id,
                'invoiceId' => $invoice->id,
                'tenantId' => $this->tenantId(),
                'originalPrice' => $basePrice,
                'discountAmount' => $discountAmount,
                'netPayable' => (float) $invoice->total,
            ]);
        }

        if ($paymentSummary !== null) {
            $this->audit('payment_recorded', $invoice, [
                'summary' => sprintf(
                    'Collected Rs. %s via %s for %s at booking (token %s).',
                    number_format($paymentSummary['amount'], 2),
                    strtoupper($paymentSummary['method']),
                    $invoice->invoice_number,
                    $appointment->token_number,
                ),
                'amount' => $paymentSummary['amount'],
                'method' => $paymentSummary['method'],
            ]);
        }

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
                // Queue-board call: a served-patient marker, not a pipeline
                // state. Guarded to pre-acquisition states so a completed,
                // cancelled or no-show study can never be announced to the
                // waiting room. Re-calling re-stamps called_at, which every
                // polling TV picks up as a fresh announcement.
                if (! in_array($appointment->state(), [
                    StudyState::Booked,
                    StudyState::CheckedIn,
                    StudyState::Preparing,
                    StudyState::InProgress,
                ], true)) {
                    throw ValidationException::withMessages([
                        'call' => __('Only waiting or in-suite studies can be called to the queue board.'),
                    ]);
                }

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

        // AI screening triage (TypeSafe System One / Jev, advisory only).
        // Runs AFTER the deterministic gate is recorded and can never change
        // it; a failure here must not fail the screening submission.
        try {
            $this->triage->evaluateAndStore($appointment);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Screening triage failed after submission', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->audit('screening_submitted', $appointment, [
            'summary' => "Safety screening recorded for #{$appointment->token_number} ({$appointment->patientDisplayName()}).",
        ]);

        return $this->ok(['study' => ApiShape::appointment($appointment->fresh(self::eager()))]);
    }

    /**
     * Re-run the AI screening triage (e.g. after the clinician adjusts a
     * risky answer or records an override). Same advisory contract as the
     * automatic run at submission: it can never change the deterministic
     * clearance gate.
     */
    public function rerunTriage(Request $request, Appointment $appointment): JsonResponse
    {
        $this->denyUnless('study screen');

        if ($appointment->business_id !== $this->tenantId()) {
            abort(404);
        }

        if ($appointment->screeningAnswers()->count() === 0) {
            throw ValidationException::withMessages([
                'answers' => 'No screening answers recorded for this study yet.',
            ]);
        }
        $result = $this->triage->evaluateAndStore($appointment);

        $this->audit('screening_triage_rerun', $appointment, [
            'summary' => "AI screening triage re-run for #{$appointment->token_number}.",
            'decision' => $result['decision'] ?? null,
            'degraded' => $result === null,
        ]);

        return $this->ok([
            'triage' => $result,
            'study' => ApiShape::appointment($appointment->fresh(self::eager())),
        ]);
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

    /**
     * Settle the booking invoice at booking time. Validation rules (all money
     * recomputed server-side from the tenant's configured price):
     *
     *  - payable = 0 (100% discount): the booking is already settled by the
     *    discount. ZERO IS A VALID MONEY VALUE — no method is demanded and no
     *    payment row is written, but collecting anything against it is refused.
     *  - payable > 0: partial → 0 < amountPaid < payable; paid → amountPaid ==
     *    payable (over-collection is a POS concern, mirror of the POS balance
     *    rule); the method must be one of THIS tenant's ACTIVE methods.
     *
     * Runs inside the booking transaction; returns null when no money moved.
     */
    private function settleBookingPayment(\App\Models\Invoice $invoice, array $payment): ?array
    {
        $payableMinor = BookingMoney::toMinor($invoice->total);
        $status = (string) $payment['status'];

        // Absent and 0 are different things: `?? 0` would make an omitted
        // amount indistinguishable from an explicit zero. Normalise once.
        $hasAmount = array_key_exists('amountPaid', $payment)
            && $payment['amountPaid'] !== null
            && $payment['amountPaid'] !== '';
        $amountMinor = $hasAmount ? BookingMoney::toMinor($payment['amountPaid']) : 0;

        if ($payableMinor <= 0) {
            if ($status === 'partial') {
                throw ValidationException::withMessages([
                    'payment.status' => 'This study is fully discounted — there is nothing left to pay.',
                ]);
            }

            if ($amountMinor > 0) {
                throw ValidationException::withMessages([
                    'payment.amountPaid' => BookingMoney::ERROR_NOTHING_TO_COLLECT,
                ]);
            }

            // Zero-payable booking: accepted, settled, no money recorded.
            return null;
        }

        if ($amountMinor <= 0) {
            throw ValidationException::withMessages([
                'payment.amountPaid' => BookingMoney::ERROR_AMOUNT_REQUIRED,
            ]);
        }

        if (empty($payment['method'])) {
            throw ValidationException::withMessages([
                'payment.method' => 'A payment method is required for this payment status.',
            ]);
        }

        // Tenant-scoped lookup: another clinic's method id must not resolve.
        $method = PaymentMethod::forClinic($this->tenantId())->findOrFail($payment['method']);

        if (! $method->is_active) {
            throw ValidationException::withMessages(['payment.method' => 'The selected payment method is inactive.']);
        }

        if ($status === 'partial' && $amountMinor >= $payableMinor) {
            throw ValidationException::withMessages([
                'payment.amountPaid' => 'A partial payment must be less than the payable amount (Rs. '.BookingMoney::format($invoice->total).').',
            ]);
        }

        if ($status === 'paid' && $amountMinor !== $payableMinor) {
            throw ValidationException::withMessages([
                'payment.amountPaid' => $amountMinor > $payableMinor
                    ? BookingMoney::ERROR_AMOUNT_EXCEEDS_PAYABLE.' (Rs. '.BookingMoney::format($invoice->total).').'
                    : BookingMoney::ERROR_AMOUNT_MUST_MATCH_PAYABLE.' (Rs. '.BookingMoney::format($invoice->total).').',
            ]);
        }

        $amount = BookingMoney::fromMinor($amountMinor);

        app(\App\Services\InvoicePaymentService::class)->record(
            $invoice,
            $amount,
            $method->code,
            $payment['reference'] ?? null,
            Auth::id(),
        );

        return ['amount' => $amount, 'method' => $method->code, 'reference' => $payment['reference'] ?? null];
    }

}
