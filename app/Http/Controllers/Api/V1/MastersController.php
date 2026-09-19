<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ApiShape;
use App\Models\Appointment;
use App\Models\InvoicePayment;
use App\Models\Modality;
use App\Models\PaymentMethod;
use App\Models\Referrer;
use App\Models\ReportTemplate;
use App\Models\Room;
use App\Models\Service;
use App\Models\ScreeningForm;
use App\Models\ScreeningQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Master data maintained by clinic admins: modalities, rooms, procedures
 * (services), referrers, screening forms and report templates.
 */
class MastersController extends BaseApiController
{
    // ==================== modalities ====================

    public function storeModality(Request $request): JsonResponse
    {
        $this->denyUnless('modality create');

        $validated = $this->validateModality($request);

        $modality = Modality::create([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'color' => $validated['color'] ?? '#0080b6',
            'buffer_minutes' => (int) ($validated['bufferMinutes'] ?? 0),
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'business_id' => $this->tenantId(),
            'created_by' => auth()->id(),
        ]);

        return response()->json(['data' => ['modality' => ApiShape::modality($modality)]], 201);
    }

    public function updateModality(Request $request, Modality $modality): JsonResponse
    {
        $this->denyUnless('modality edit');

        if ($modality->business_id !== $this->tenantId()) {
            abort(404);
        }

        $validated = $this->validateModality($request);

        $modality->update([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'color' => $validated['color'] ?? $modality->color,
            'buffer_minutes' => (int) ($validated['bufferMinutes'] ?? $modality->buffer_minutes),
            'is_active' => (bool) ($validated['isActive'] ?? $modality->is_active),
        ]);

        return $this->ok(['modality' => ApiShape::modality($modality->fresh())]);
    }

    public function destroyModality(Modality $modality): JsonResponse
    {
        $this->denyUnless('modality delete');

        if ($modality->business_id !== $this->tenantId()) {
            abort(404);
        }

        if ($modality->procedures()->exists()) {
            abort(422, 'Cannot delete a modality that has procedures assigned.');
        }

        $modality->delete();

        return $this->ok(['deleted' => true]);
    }

    private function validateModality(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:10'],
            'color' => ['nullable', 'string', 'max:9'],
            'bufferMinutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'isActive' => ['nullable', 'boolean'],
        ]);
    }

    // ==================== services (procedures) ====================

    public function storeService(Request $request): JsonResponse
    {
        $this->denyUnless('service create');

        $validated = $this->validateService($request);

        $service = Service::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'modality_id' => $validated['modalityId'],
            'category_id' => $this->defaultCategoryId(),
            'price' => (float) $validated['price'],
            'duration' => (string) ($validated['durationMinutes'] ?? 15),
            'duration_minutes' => (int) ($validated['durationMinutes'] ?? 15),
            'preparation_instructions' => $validated['preparationInstructions'] ?? 'No special preparation needed.',
            'requires_screening' => (bool) ($validated['requiresScreening'] ?? false),
            'contrast_type' => ($validated['requiresContrast'] ?? false) ? 'intravenous' : 'none',
            'is_bookable_online' => true,
            'business_id' => $this->tenantId(),
            'created_by' => auth()->id(),
        ]);

        return response()->json(['data' => ['service' => ApiShape::service($service->fresh('modality'))]], 201);
    }

    public function updateService(Request $request, Service $service): JsonResponse
    {
        $this->denyUnless('service edit');

        if ($service->business_id !== $this->tenantId()) {
            abort(404);
        }

        $validated = $this->validateService($request);

        $service->update([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'modality_id' => $validated['modalityId'],
            'price' => (float) $validated['price'],
            'duration_minutes' => (int) ($validated['durationMinutes'] ?? $service->duration_minutes),
            'preparation_instructions' => $validated['preparationInstructions'] ?? $service->preparation_instructions,
            'requires_screening' => (bool) ($validated['requiresScreening'] ?? $service->requires_screening),
            'contrast_type' => ($validated['requiresContrast'] ?? $service->contrast_type !== 'none') ? 'intravenous' : 'none',
        ]);

        return $this->ok(['service' => ApiShape::service($service->fresh('modality'))]);
    }

    public function destroyService(Service $service): JsonResponse
    {
        $this->denyUnless('service delete');

        if ($service->business_id !== $this->tenantId()) {
            abort(404);
        }

        // Soft-deleting keeps past studies renderable, but a service still
        // referenced by booked studies must not vanish from the booking
        // catalog — history would lose its procedure name/price anchor.
        if ($service->appointments()->exists()) {
            abort(422, 'Cannot delete a service that has studies booked against it. Deactivate it instead.');
        }

        $service->delete();

        return $this->ok(['deleted' => true]);
    }

    /** Tenant-scoped modality existence: another clinic's modality id must fail validation. */
    private function modalityRule(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'integer',
            Rule::exists('modalities', 'id')->where(fn ($q) => $q->where('business_id', $this->tenantId())),
        ];
    }

    private function validateService(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:40'],
            'modalityId' => $this->modalityRule(),
            'price' => ['required', 'numeric', 'min:0'],
            'durationMinutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'preparationInstructions' => ['nullable', 'string', 'max:2000'],
            'requiresScreening' => ['nullable', 'boolean'],
            'requiresContrast' => ['nullable', 'boolean'],
        ]);
    }

    private function defaultCategoryId(): int
    {
        $category = \App\Models\Category::firstOrCreate(
            ['name' => 'Radiology', 'business_id' => $this->tenantId()],
            ['created_by' => auth()->id()]
        );

        return $category->id;
    }

    // ==================== referrers ====================

    public function storeReferrer(Request $request): JsonResponse
    {
        $this->denyUnless('referrer create');

        $validated = $this->validateReferrer($request);

        $referrer = Referrer::create([
            ...$this->referrerPayload($validated),
            'is_active' => true,
            'business_id' => $this->tenantId(),
            'created_by' => auth()->id(),
        ]);

        return response()->json(['data' => ['referrer' => ApiShape::referrer($referrer)]], 201);
    }

    public function updateReferrer(Request $request, Referrer $referrer): JsonResponse
    {
        $this->denyUnless('referrer edit');

        if ($referrer->business_id !== $this->tenantId()) {
            abort(404);
        }

        $referrer->update($this->referrerPayload($this->validateReferrer($request)));

        return $this->ok(['referrer' => ApiShape::referrer($referrer->fresh())]);
    }

    /** Frontend `clinicName` maps to the `clinic` column. */
    private function referrerPayload(array $validated): array
    {
        return collect($validated)
            ->mapWithKeys(fn ($v, $k) => [\Str::snake($k) === 'clinic_name' ? 'clinic' : \Str::snake($k) => $v])
            ->all();
    }

    public function destroyReferrer(Referrer $referrer): JsonResponse
    {
        $this->denyUnless('referrer delete');

        if ($referrer->business_id !== $this->tenantId()) {
            abort(404);
        }

        $referrer->delete();

        return $this->ok(['deleted' => true]);
    }

    private function validateReferrer(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'clinicName' => ['nullable', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
    }

    // ==================== screening forms ====================

    public function storeScreeningForm(Request $request): JsonResponse
    {
        $this->denyUnless('report template create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'modalityId' => $this->modalityRule(false),
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.questionText' => ['required', 'string', 'max:1000'],
            'questions.*.helpText' => ['nullable', 'string', 'max:500'],
            'questions.*.answerType' => ['required', 'in:boolean,select,text'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.riskValue' => ['nullable', 'string', 'max:50'],
            'questions.*.isRiskBlocking' => ['nullable', 'boolean'],
        ]);

        $form = DB::transaction(function () use ($validated) {
            $form = ScreeningForm::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']).'-'.Str::random(4),
                'description' => $validated['description'] ?? null,
                'modality_id' => $validated['modalityId'] ?? null,
                'is_active' => true,
                'business_id' => $this->tenantId(),
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['questions'] as $i => $q) {
                $form->questions()->create([
                    'question_text' => $q['questionText'],
                    'help_text' => $q['helpText'] ?? null,
                    'answer_type' => $q['answerType'],
                    'options' => $q['options'] ?? null,
                    'risk_value' => $q['riskValue'] ?? 'yes',
                    'is_risk_blocking' => (bool) ($q['isRiskBlocking'] ?? true),
                    'sort_order' => $i + 1,
                ]);
            }

            return $form;
        });

        return response()->json(['data' => ['form' => ApiShape::screeningForm($form->fresh('questions'))]], 201);
    }

    public function updateScreeningForm(Request $request, ScreeningForm $form): JsonResponse
    {
        $this->denyUnless('report template edit');

        if ($form->business_id !== $this->tenantId()) {
            abort(404);
        }

        $validated = $request->validate([
            'questions' => ['sometimes', 'array', 'min:1'],
            'questions.*.questionText' => ['required_with:questions', 'string', 'max:1000'],
            'questions.*.helpText' => ['nullable', 'string', 'max:500'],
            'questions.*.answerType' => ['required_with:questions', 'in:boolean,select,text'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.riskValue' => ['nullable', 'string', 'max:50'],
            'questions.*.isRiskBlocking' => ['nullable', 'boolean'],
        ]);

        // Questions are versioned wholesale: replace the active set atomically.
        if (isset($validated['questions'])) {
            DB::transaction(function () use ($form, $validated) {
                $form->questions()->delete();
                foreach ($validated['questions'] as $i => $q) {
                    $form->questions()->create([
                        'question_text' => $q['questionText'],
                        'help_text' => $q['helpText'] ?? null,
                        'answer_type' => $q['answerType'],
                        'options' => $q['options'] ?? null,
                        'risk_value' => $q['riskValue'] ?? 'yes',
                        'is_risk_blocking' => (bool) ($q['isRiskBlocking'] ?? true),
                        'sort_order' => $i + 1,
                    ]);
                }
            });
        }

        return $this->ok(['form' => ApiShape::screeningForm($form->fresh('questions'))]);
    }

    public function toggleScreeningForm(ScreeningForm $form): JsonResponse
    {
        $this->denyUnless('report template edit');

        if ($form->business_id !== $this->tenantId()) {
            abort(404);
        }

        $form->update(['is_active' => ! $form->is_active]);

        return $this->ok(['form' => ApiShape::screeningForm($form->fresh('questions'))]);
    }

    public function destroyScreeningForm(ScreeningForm $form): JsonResponse
    {
        $this->denyUnless('report template delete');

        if ($form->business_id !== $this->tenantId()) {
            abort(404);
        }

        $form->delete();

        return $this->ok(['deleted' => true]);
    }

    // ==================== report templates ====================

    public function storeReportTemplate(Request $request): JsonResponse
    {
        $validated = $this->validateReportTemplate($request);
        $scope = $validated['scope'] ?? 'personal';

        // A tenant-wide template is governed clinical content; a radiologist's
        // own private template only needs report-authoring rights.
        if ($scope === 'tenant') {
            $this->denyUnless('report template create');
        } else {
            $this->denyUnlessAny(['report template create', 'report edit'], 'report template create');
        }

        $template = ReportTemplate::create([
            ...$this->templateColumns($validated),
            'modality_id' => $validated['modalityId'],
            'service_id' => $validated['serviceId'] ?? null,
            'body_region' => $validated['bodyRegion'] ?? null,
            'age_group' => $validated['ageGroup'] ?? null,
            'sex' => $validated['sex'] ?? null,
            'contrast' => $validated['contrast'] ?? null,
            'scope' => $scope,
            'is_default' => (bool) ($validated['isDefault'] ?? false),
            'version' => 1,
            'business_id' => $this->tenantId(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $this->audit('report_template_saved', $template, ['summary' => "Created report template \"{$template->name}\""]);

        return response()->json(['data' => ['template' => ApiShape::reportTemplate($template)]], 201);
    }

    public function updateReportTemplate(Request $request, ReportTemplate $template): JsonResponse
    {
        $this->guardTemplate($template, 'report template edit');

        $validated = $this->validateReportTemplate($request);

        $template->update([
            ...$this->templateColumns($validated),
            'modality_id' => $validated['modalityId'],
            'service_id' => $validated['serviceId'] ?? null,
            'body_region' => $validated['bodyRegion'] ?? null,
            'age_group' => $validated['ageGroup'] ?? null,
            'sex' => $validated['sex'] ?? null,
            'contrast' => $validated['contrast'] ?? null,
            'is_default' => (bool) ($validated['isDefault'] ?? $template->is_default),
            // Every edit is a new revision: reports record which revision they
            // were authored against, so clinical text stays traceable.
            'version' => ((int) $template->version) + 1,
            'updated_by' => auth()->id(),
        ]);

        $this->audit('report_template_saved', $template, ['summary' => "Updated report template \"{$template->name}\" to v{$template->version}"]);

        return $this->ok(['template' => ApiShape::reportTemplate($template->fresh(['modality', 'serviceData', 'author']))]);
    }

    public function destroyReportTemplate(ReportTemplate $template): JsonResponse
    {
        $this->guardTemplate($template, 'report template delete');

        $template->delete();

        $this->audit('report_template_deleted', $template, ['summary' => "Deleted report template \"{$template->name}\""]);

        return $this->ok(['deleted' => true]);
    }

    /**
     * Archive (retire) a template. Historical reports keep referencing it, so
     * archival — not deletion — is the normal retirement path.
     */
    public function archiveReportTemplate(Request $request, ReportTemplate $template): JsonResponse
    {
        $this->guardTemplate($template, 'report template edit');

        $validated = $request->validate(['archived' => ['sometimes', 'boolean']]);
        $archived = (bool) ($validated['archived'] ?? true);

        $template->forceFill(['is_archived' => $archived, 'updated_by' => auth()->id()])->save();

        $this->audit('report_template_archived', $template, [
            'summary' => ($archived ? 'Archived' : 'Restored')." report template \"{$template->name}\"",
        ]);

        return $this->ok(['template' => ApiShape::reportTemplate($template->fresh())]);
    }

    /** Copy a template (typically a shared one) into a new private or shared template. */
    public function duplicateReportTemplate(Request $request, ReportTemplate $template): JsonResponse
    {
        if ($template->business_id !== $this->tenantId()) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'scope' => ['sometimes', 'in:tenant,personal'],
        ]);

        $scope = $validated['scope'] ?? 'personal';

        if ($scope === 'tenant') {
            $this->denyUnless('report template create');
        } else {
            $this->denyUnlessAny(['report template create', 'report edit'], 'report template create');
        }

        $copy = $template->replicate(['deleted_at']);
        $copy->name = $validated['name'] ?? $template->name.' (copy)';
        $copy->scope = $scope;
        $copy->is_default = false;
        $copy->version = 1;
        $copy->created_by = auth()->id();
        $copy->updated_by = auth()->id();
        $copy->save();

        $this->audit('report_template_saved', $copy, ['summary' => "Duplicated report template \"{$template->name}\""]);

        return response()->json(['data' => ['template' => ApiShape::reportTemplate($copy)]], 201);
    }

    /**
     * Authorisation for a template. A radiologist may manage their OWN private
     * template with report-authoring rights; shared clinic templates need the
     * template permissions.
     */
    private function guardTemplate(ReportTemplate $template, string $sharedPermission): void
    {
        if ($template->business_id !== $this->tenantId()) {
            abort(404);
        }

        $mine = ($template->scope === 'personal') && (int) $template->created_by === (int) auth()->id();

        if ($mine) {
            $this->denyUnlessAny([$sharedPermission, 'report edit'], $sharedPermission);

            return;
        }

        $this->denyUnless($sharedPermission);
    }

    /** @return array<string,mixed> */
    private function templateColumns(array $validated): array
    {
        return [
            ...collect($validated)->only(['name', 'clinicalHistory', 'technique', 'findings', 'impression', 'recommendations'])
                ->mapWithKeys(fn ($v, $k) => [\Str::snake($k) => $v])->all(),
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'structured_fields' => \App\Support\ReportStructure::sanitizeFields($validated['structuredFields'] ?? null) ?: null,
        ];
    }

    // ==================== rooms (imaging suites) ====================

    public function storeRoom(Request $request): JsonResponse
    {
        $this->denyUnless('room create');

        $validated = $this->validateRoom($request);

        $room = Room::create([
            'name' => $validated['name'],
            'modality_id' => $validated['modalityId'],
            'location_id' => $validated['locationId'] ?? null,
            'capacity_per_slot' => (int) ($validated['capacityPerSlot'] ?? 1),
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'business_id' => $this->tenantId(),
            'created_by' => auth()->id(),
        ]);

        return response()->json(['data' => ['room' => ApiShape::room($room->fresh('modality'))]], 201);
    }

    public function updateRoom(Request $request, Room $room): JsonResponse
    {
        $this->denyUnless('room edit');

        if ($room->business_id !== $this->tenantId()) {
            abort(404);
        }

        $validated = $this->validateRoom($request);

        $room->update([
            'name' => $validated['name'],
            'modality_id' => $validated['modalityId'],
            'location_id' => $validated['locationId'] ?? null,
            'capacity_per_slot' => (int) ($validated['capacityPerSlot'] ?? $room->capacity_per_slot),
            'description' => $validated['description'] ?? $room->description,
            'is_active' => (bool) ($validated['isActive'] ?? $room->is_active),
        ]);

        return $this->ok(['room' => ApiShape::room($room->fresh('modality'))]);
    }

    public function destroyRoom(Room $room): JsonResponse
    {
        $this->denyUnless('room delete');

        if ($room->business_id !== $this->tenantId()) {
            abort(404);
        }

        // Studies booked against this suite must keep rendering their room —
        // deactivate instead of deleting the anchor out of history.
        if (Appointment::where('room_id', $room->id)->exists()) {
            abort(422, 'Cannot delete a suite that has studies booked against it. Deactivate it instead.');
        }

        $room->delete();

        return $this->ok(['deleted' => true]);
    }

    private function validateRoom(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'modalityId' => $this->modalityRule(),
            'locationId' => ['nullable', 'integer'],
            'capacityPerSlot' => ['nullable', 'integer', 'min:1', 'max:20'],
            'description' => ['nullable', 'string', 'max:2000'],
            'isActive' => ['nullable', 'boolean'],
        ]);
    }

    // ==================== payment methods ====================

    public function storePaymentMethod(Request $request): JsonResponse
    {
        $this->denyUnless('payment method create');

        $validated = $this->validatePaymentMethod($request);
        $code = strtolower($validated['code']);

        // The (business_id, code) unique index spans soft-deleted rows, so a
        // recreate of a deleted code restores the original row instead of
        // colliding with it (same semantics as the modality seeder).
        $method = PaymentMethod::withTrashed()
            ->where('business_id', $this->tenantId())
            ->where('code', $code)
            ->first();

        if ($method) {
            $method->restore();
            $method->update([
                'name' => $validated['name'],
                'is_active' => (bool) ($validated['isActive'] ?? true),
                'sort_order' => (int) ($validated['sortOrder'] ?? $method->sort_order),
            ]);

            return response()->json(['data' => ['paymentMethod' => ApiShape::paymentMethod($method->fresh())]], 201);
        }

        $method = PaymentMethod::create([
            'code' => $code,
            'name' => $validated['name'],
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
            'business_id' => $this->tenantId(),
            'created_by' => auth()->id(),
        ]);

        return response()->json(['data' => ['paymentMethod' => ApiShape::paymentMethod($method)]], 201);
    }

    public function updatePaymentMethod(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $this->denyUnless('payment method edit');

        if ($paymentMethod->business_id !== $this->tenantId()) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        // `code` is intentionally immutable: recorded payments reference it.
        $paymentMethod->update([
            'name' => $validated['name'],
            'is_active' => (bool) ($validated['isActive'] ?? $paymentMethod->is_active),
            'sort_order' => (int) ($validated['sortOrder'] ?? $paymentMethod->sort_order),
        ]);

        return $this->ok(['paymentMethod' => ApiShape::paymentMethod($paymentMethod->fresh())]);
    }

    public function destroyPaymentMethod(PaymentMethod $paymentMethod): JsonResponse
    {
        $this->denyUnless('payment method delete');

        if ($paymentMethod->business_id !== $this->tenantId()) {
            abort(404);
        }

        if (InvoicePayment::where('business_id', $this->tenantId())->where('method', $paymentMethod->code)->exists()) {
            abort(422, 'Cannot delete a payment method that has payments recorded. Deactivate it instead.');
        }

        $paymentMethod->delete();

        return $this->ok(['deleted' => true]);
    }

    private function validatePaymentMethod(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[a-z0-9_-]+$/i'],
            'name' => ['required', 'string', 'max:80'],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
    }

    private function validateReportTemplate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:60'],
            'modalityId' => $this->modalityRule(),
            'serviceId' => [
                'nullable', 'integer',
                Rule::exists('services', 'id')->where(fn ($q) => $q->where('business_id', $this->tenantId())),
            ],
            'bodyRegion' => ['nullable', 'string', 'max:60'],
            'ageGroup' => ['nullable', 'in:'.implode(',', \App\Support\AgeGroup::all())],
            'sex' => ['nullable', 'in:male,female'],
            'contrast' => ['nullable', 'in:with,without,both'],
            'scope' => ['sometimes', 'in:tenant,personal'],
            'isDefault' => ['sometimes', 'boolean'],
            'structuredFields' => ['sometimes', 'array', 'max:60'],
            'clinicalHistory' => ['nullable', 'string', 'max:5000'],
            'technique' => ['nullable', 'string', 'max:5000'],
            // A findings/impression skeleton may legitimately be empty: a
            // template is a drafting aid, not a mandatory fill-in form.
            'findings' => ['nullable', 'string'],
            'impression' => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string', 'max:3000'],
        ]);
    }
}
