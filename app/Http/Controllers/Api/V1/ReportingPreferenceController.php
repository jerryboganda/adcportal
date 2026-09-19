<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ReportingPreference;
use App\Models\ReportingSavedView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * A radiologist's reporting setup, per user and per clinic.
 *
 * Everything here is scoped to the ACTING user inside the ACTING tenant: a
 * preference or saved view belonging to someone else (or to the same person in
 * another clinic) is a 404, never a silent read or a silent overwrite.
 *
 * Nothing stored here is patient data — a saved view is a set of queue filters.
 */
class ReportingPreferenceController extends BaseApiController
{
    // ==================== preferences ====================

    public function show(): JsonResponse
    {
        $this->denyUnless('report manage');

        return $this->ok([
            'preferences' => $this->shape(ReportingPreference::forUser($this->tenantId(), (int) Auth::id())),
            'views' => $this->views(),
            'languages' => ReportingPreference::DICTATION_LANGUAGES,
            'tabs' => ReportingPreference::DEFAULT_TABS,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->denyUnless('report manage');

        // `sometimes` throughout: the workspace saves the one preference the
        // radiologist just changed, not a whole record it might have loaded
        // before a colleague's change landed.
        $validated = $request->validate([
            'dictationLanguage' => ['sometimes', 'string', 'in:'.implode(',', ReportingPreference::DICTATION_LANGUAGES)],
            'dictationProvider' => ['sometimes', 'string', 'in:'.implode(',', ReportingPreference::DICTATION_PROVIDERS)],
            'templateAutoload' => ['sometimes', 'boolean'],
            'defaultTab' => ['sometimes', 'string', 'in:'.implode(',', ReportingPreference::DEFAULT_TABS)],
        ]);

        if ($validated === []) {
            throw ValidationException::withMessages([
                'preferences' => 'Nothing to update: send at least one preference.',
            ]);
        }

        $preference = ReportingPreference::forUser($this->tenantId(), (int) Auth::id());

        $preference->fill([
            'dictation_language' => $validated['dictationLanguage'] ?? $preference->dictation_language,
            'dictation_provider' => $validated['dictationProvider'] ?? $preference->dictation_provider,
            'template_autoload' => $validated['templateAutoload'] ?? $preference->template_autoload,
            'default_tab' => $validated['defaultTab'] ?? $preference->default_tab,
        ]);

        $preference->save();

        return $this->ok(['preferences' => $this->shape($preference)]);
    }

    // ==================== saved views ====================

    public function storeView(Request $request): JsonResponse
    {
        $this->denyUnless('report manage');

        $validated = $this->validateView($request, requireName: true);

        $view = ReportingSavedView::forClinic($this->tenantId())
            ->where('user_id', (int) Auth::id())
            ->where('name', $validated['name'])
            ->first();

        // Saving under a name that already exists REPLACES that view — the
        // button says "save view", so a duplicate would be a surprise.
        $view ??= new ReportingSavedView([
            'business_id' => $this->tenantId(),
            'user_id' => (int) Auth::id(),
            'name' => $validated['name'],
        ]);

        $view->filters = $validated['filters'];
        $view->save();

        return $this->ok(['view' => $this->shapeView($view), 'views' => $this->views()]);
    }

    public function updateView(Request $request, ReportingSavedView $view): JsonResponse
    {
        $this->denyUnless('report manage');
        $this->assertOwned($view);

        $validated = $this->validateView($request, requireName: false);

        if ($validated === []) {
            throw ValidationException::withMessages([
                'view' => 'Nothing to update: send a new name, a new filter set, or both.',
            ]);
        }

        if (isset($validated['name']) && $validated['name'] !== $view->name) {
            $clash = ReportingSavedView::forClinic($this->tenantId())
                ->where('user_id', (int) Auth::id())
                ->where('name', $validated['name'])
                ->where('id', '!=', $view->id)
                ->exists();

            if ($clash) {
                throw ValidationException::withMessages(['name' => 'You already have a view with that name.']);
            }

            $view->name = $validated['name'];
        }

        if (isset($validated['filters'])) {
            $view->filters = $validated['filters'];
        }

        $view->save();

        return $this->ok(['view' => $this->shapeView($view), 'views' => $this->views()]);
    }

    public function destroyView(ReportingSavedView $view): JsonResponse
    {
        $this->denyUnless('report manage');
        $this->assertOwned($view);

        $view->delete();

        return $this->ok(['views' => $this->views()]);
    }

    // ==================== internals ====================

    /** @param array<string,mixed> $payload */
    private function validateView(Request $request, bool $requireName): array
    {
        $rules = [
            'filters' => ['sometimes', 'array'],
            'filters.tab' => ['sometimes', 'string', 'in:'.implode(',', ReportingPreference::DEFAULT_TABS)],
            'filters.q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'filters.priority' => ['sometimes', 'string', 'in:all,routine,urgent,stat'],
            'filters.modalityId' => ['sometimes', 'nullable', 'integer'],
            'filters.reportStatus' => ['sometimes', 'nullable', 'string', 'max:40'],
            'filters.assignee' => ['sometimes', 'nullable', 'string', 'max:40'],
            'filters.sort' => ['sometimes', 'string', 'in:priority,oldest,newest'],
            'filters.from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'filters.to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];

        if ($requireName) {
            $rules['name'] = ['required', 'string', 'max:60'];
            $rules['filters'] = ['required', 'array'];
        } else {
            $rules['name'] = ['sometimes', 'string', 'max:60'];
        }

        return $request->validate($rules);
    }

    /** Another user's view (or another clinic's) must not be addressable. */
    private function assertOwned(ReportingSavedView $view): void
    {
        if ($view->business_id !== $this->tenantId() || (int) $view->user_id !== (int) Auth::id()) {
            abort(404);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function views(): array
    {
        return ReportingSavedView::forClinic($this->tenantId())
            ->where('user_id', (int) Auth::id())
            ->orderBy('name')
            ->get()
            ->map(fn (ReportingSavedView $v) => $this->shapeView($v))
            ->all();
    }

    /** @return array<string, mixed> */
    private function shape(ReportingPreference $preference): array
    {
        return [
            'dictationLanguage' => $preference->dictation_language,
            'dictationProvider' => $preference->dictation_provider,
            'templateAutoload' => (bool) $preference->template_autoload,
            'defaultTab' => $preference->default_tab,
        ];
    }

    /** @return array<string, mixed> */
    private function shapeView(ReportingSavedView $view): array
    {
        return [
            'id' => (string) $view->id,
            'name' => $view->name,
            'filters' => $view->filters ?? [],
        ];
    }
}
