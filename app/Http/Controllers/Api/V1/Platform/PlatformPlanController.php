<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Resources\ApiShape;
use App\Models\AuditLog;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Plan catalog management (control plane). Plans carry the entitlement
 * limits the server enforces; prices are catalog data only — there is no
 * payment gateway and nothing pretends to charge.
 */
class PlatformPlanController extends PlatformController
{
    /** Public catalog for signup + the subscription gate (no auth). */
    public function publicIndex(): JsonResponse
    {
        $plans = Plan::where('is_active', true)->orderBy('price_monthly')->get();

        return $this->ok(['plans' => $plans->map(fn ($p) => [
            ...ApiShape::plan($p),
            'maxStudiesPerMonth' => $p->max_studies_per_month,
            'maxStorageMb' => $p->max_storage_mb,
            'maxLocations' => $p->max_locations,
            'features' => (array) ($p->features ?? []),
        ])->all()]);
    }

    public function index(): JsonResponse
    {
        $this->denyUnlessCapability('tenants.view');

        $plans = Plan::withCount('businesses')->orderBy('price_monthly')->get();

        return $this->ok(['plans' => $plans->map(fn ($p) => [
            ...ApiShape::plan($p),
            'maxStudiesPerMonth' => $p->max_studies_per_month,
            'maxStorageMb' => $p->max_storage_mb,
            'maxLocations' => $p->max_locations,
            'features' => (array) ($p->features ?? []),
            'subscribers' => (int) $p->businesses_count,
        ])->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->denyUnlessCapability('plans.manage');

        $attributes = $this->attributes($request);

        $plan = Plan::create([
            ...$attributes,
            'slug' => $attributes['slug'] ?? Str::slug($attributes['name']),
        ]);

        AuditLog::record('plan_created', $plan, ['summary' => "Plan {$plan->name} created."]);

        return response()->json(['data' => ['plan' => ApiShape::plan($plan)]], 201);
    }

    public function update(Request $request, Plan $plan): JsonResponse
    {
        $this->denyUnlessCapability('plans.manage');

        $plan->update($this->attributes($request, $plan));

        AuditLog::record('plan_updated', $plan, ['summary' => "Plan {$plan->name} updated."]);

        return $this->ok(['plan' => ApiShape::plan($plan->fresh())]);
    }

    /** Validate the camelCase SPA payload and map onto the plan's columns. */
    private function attributes(Request $request, ?Plan $plan = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('plans', 'slug')->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'priceMonthly' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:8'],
            'trialDays' => ['required', 'integer', 'min:0', 'max:365'],
            'maxUsers' => ['nullable', 'integer', 'min:1'],
            'maxStudiesPerMonth' => ['nullable', 'integer', 'min:1'],
            'maxStorageMb' => ['nullable', 'integer', 'min:1'],
            'maxLocations' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $knownFeatures = array_keys(config('ris.features', []));

        return collect($validated)
            ->mapWithKeys(fn ($value, $key) => [Str::snake($key) => $value])
            ->when(isset($validated['features']), fn ($c) => $c->put('features', collect($validated['features'])
                ->filter(fn ($v, $k) => in_array($k, $knownFeatures, true))
                ->map(fn ($v) => (bool) $v)
                ->all()))
            ->all();
    }
}
