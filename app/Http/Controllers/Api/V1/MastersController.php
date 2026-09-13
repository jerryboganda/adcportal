<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ApiShape;
use App\Models\Modality;
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

        $service->delete();

        return $this->ok(['deleted' => true]);
    }

    private function validateService(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:40'],
            'modalityId' => ['required', 'integer', 'exists:modalities,id'],
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
            'modalityId' => ['nullable', 'integer', 'exists:modalities,id'],
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
        $this->denyUnless('report template create');

        $validated = $this->validateReportTemplate($request);

        $template = ReportTemplate::create([
            ...collect($validated)->only(['name', 'clinicalHistory', 'technique', 'findings', 'impression', 'recommendations'])
                ->mapWithKeys(fn ($v, $k) => [\Str::snake($k) => $v])->all(),
            'modality_id' => $validated['modalityId'],
            'business_id' => $this->tenantId(),
            'created_by' => auth()->id(),
        ]);

        return response()->json(['data' => ['template' => ApiShape::reportTemplate($template)]], 201);
    }

    public function updateReportTemplate(Request $request, ReportTemplate $template): JsonResponse
    {
        $this->denyUnless('report template edit');

        if ($template->business_id !== $this->tenantId()) {
            abort(404);
        }

        $validated = $this->validateReportTemplate($request);

        $template->update([
            ...collect($validated)->only(['name', 'clinicalHistory', 'technique', 'findings', 'impression', 'recommendations'])
                ->mapWithKeys(fn ($v, $k) => [\Str::snake($k) => $v])->all(),
            'modality_id' => $validated['modalityId'],
        ]);

        return $this->ok(['template' => ApiShape::reportTemplate($template->fresh())]);
    }

    public function destroyReportTemplate(ReportTemplate $template): JsonResponse
    {
        $this->denyUnless('report template delete');

        if ($template->business_id !== $this->tenantId()) {
            abort(404);
        }

        $template->delete();

        return $this->ok(['deleted' => true]);
    }

    private function validateReportTemplate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'modalityId' => ['required', 'integer', 'exists:modalities,id'],
            'clinicalHistory' => ['nullable', 'string', 'max:5000'],
            'technique' => ['nullable', 'string', 'max:5000'],
            'findings' => ['nullable', 'string'],
            'impression' => ['required', 'string'],
            'recommendations' => ['nullable', 'string', 'max:3000'],
        ]);
    }
}
