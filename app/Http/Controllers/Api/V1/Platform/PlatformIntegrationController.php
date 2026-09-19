<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Location;
use App\Models\TenantIntegration;
use App\Services\FeatureResolver;
use App\Services\TenantIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant integration registry from the control plane (master-prompt §33/§44).
 *
 * Every integration is tenant-owned (optionally facility-scoped), its type is
 * mapped to the entitlement that unlocks it, and its credentials are stored
 * encrypted and never returned — only presence and a mask.
 */
class PlatformIntegrationController extends PlatformController
{
    public function index(Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('tenants.view');

        return $this->ok($this->payload($tenant));
    }

    public function store(Request $request, Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('tenants.manage');

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(TenantIntegrationService::catalog()))],
            'name' => ['required', 'string', 'max:120'],
            'facilityId' => ['sometimes', 'nullable', 'integer'],
            'config' => ['sometimes', 'array', 'max:24'],
            'config.*' => ['nullable', 'string', 'max:1024'],
            'secrets' => ['sometimes', 'array', 'max:24'],
            'secrets.*' => ['nullable', 'string', 'max:4096'],
        ]);

        $type = $validated['type'];
        $this->denyUnlessFeature($tenant, $type);
        $this->assertFacilityBelongsToTenant($tenant, $validated['facilityId'] ?? null);

        $secrets = $this->filterSecretKeys($type, $validated['secrets'] ?? []);
        $config = $validated['config'] ?? [];

        abort_if(
            TenantIntegration::where('business_id', $tenant->id)->where('name', $validated['name'])->exists(),
            422,
            'This tenant already has an integration with that name.'
        );

        $integration = TenantIntegration::create([
            'business_id' => $tenant->id,
            'location_id' => $validated['facilityId'] ?? null,
            'type' => $type,
            'name' => $validated['name'],
            'config' => $config,
            'secrets' => $secrets,
            'status' => TenantIntegrationService::deriveStatus($type, $config, $secrets),
            'created_by' => $this->actor()->id,
        ]);

        AuditLog::record('tenant_integration_created', $integration, [
            'summary' => "Integration {$integration->name} ({$type}) created for {$tenant->name}; "
                .count($secrets).' secret key(s) stored encrypted.'
                .' Acting platform user: '.$this->actor()->email.'.',
        ], $tenant->id);

        return response()->json([
            'data' => [...$this->payload($tenant), 'integration' => TenantIntegrationService::shape($integration->fresh())],
        ], 201);
    }

    public function update(Request $request, Business $tenant, TenantIntegration $integration): JsonResponse
    {
        $this->denyUnlessCapability('tenants.manage');
        abort_unless($integration->business_id === $tenant->id, 404);

        $this->denyUnlessFeature($tenant, $integration->type);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'facilityId' => ['sometimes', 'nullable', 'integer'],
            'config' => ['sometimes', 'array', 'max:24'],
            'config.*' => ['nullable', 'string', 'max:1024'],
            'secrets' => ['sometimes', 'array', 'max:24'],
            'secrets.*' => ['nullable', 'string', 'max:4096'],
        ]);

        $this->assertFacilityBelongsToTenant($tenant, $validated['facilityId'] ?? null);

        if (isset($validated['name']) && $validated['name'] !== $integration->name) {
            abort_if(
                TenantIntegration::where('business_id', $tenant->id)
                    ->where('name', $validated['name'])
                    ->where('id', '!=', $integration->id)
                    ->exists(),
                422,
                'This tenant already has an integration with that name.'
            );
            $integration->name = $validated['name'];
        }

        if (array_key_exists('facilityId', $validated)) {
            $integration->location_id = $validated['facilityId'];
        }

        if (array_key_exists('config', $validated)) {
            $integration->config = $validated['config'];
        }

        if (array_key_exists('secrets', $validated)) {
            // Merge: omitted secret keys keep their stored value (never echoed back).
            $existing = is_array($integration->secrets) ? $integration->secrets : [];
            $integration->secrets = array_merge($existing, $this->filterSecretKeys($integration->type, $validated['secrets']));
        }

        $integration->status = TenantIntegrationService::deriveStatus(
            $integration->type,
            is_array($integration->config) ? $integration->config : [],
            is_array($integration->secrets) ? $integration->secrets : []
        );
        $integration->save();

        AuditLog::record('tenant_integration_updated', $integration, [
            'summary' => "Integration {$integration->name} ({$integration->type}) updated for {$tenant->name}: "
                .implode(', ', array_keys($validated)).'.'
                .' Acting platform user: '.$this->actor()->email.'.',
        ], $tenant->id);

        return $this->ok([...$this->payload($tenant), 'integration' => TenantIntegrationService::shape($integration->fresh())]);
    }

    /**
     * Replace the stored credentials wholesale. The new values are never
     * echoed back — the response only reports which keys are now present.
     */
    public function rotateSecrets(Request $request, Business $tenant, TenantIntegration $integration): JsonResponse
    {
        $this->denyUnlessCapability('tenants.manage');
        abort_unless($integration->business_id === $tenant->id, 404);

        $validated = $request->validate([
            'secrets' => ['required', 'array', 'min:1', 'max:24'],
            'secrets.*' => ['nullable', 'string', 'max:4096'],
        ]);

        $secrets = $this->filterSecretKeys($integration->type, $validated['secrets']);

        abort_if($secrets === [], 422, 'None of the supplied keys are credentials for this integration type.');

        $integration->secrets = $secrets;
        $integration->status = TenantIntegrationService::deriveStatus(
            $integration->type,
            is_array($integration->config) ? $integration->config : [],
            $secrets
        );
        $integration->save();

        AuditLog::record('tenant_integration_secret_rotated', $integration, [
            'summary' => "Credentials rotated for integration {$integration->name} ({$integration->type}) on {$tenant->name}: "
                .implode(', ', array_keys($secrets)).'.'
                .' Acting platform user: '.$this->actor()->email.'.',
        ], $tenant->id);

        return $this->ok([...$this->payload($tenant), 'integration' => TenantIntegrationService::shape($integration->fresh())]);
    }

    /** Run the type's real health check and persist the outcome. */
    /** Real end-to-end test: send a synthetic event through the channel. */
    public function testDelivery(Business $tenant, TenantIntegration $integration): JsonResponse
    {
        $this->denyUnlessCapability('tenants.manage');
        abort_unless($integration->business_id === $tenant->id, 404);

        $result = TenantIntegrationService::testDelivery($integration);

        AuditLog::record('tenant_integration_tested', $tenant, [
            'summary' => "Test delivery through {$integration->name} ({$integration->type}) on {$tenant->name}: {$result['status']}."
                .' Acting platform user: '.$this->actor()->email.'.',
        ], $tenant->id);

        return $this->ok($this->payload($tenant) + ['test' => $result]);
    }

    public function probe(Business $tenant, TenantIntegration $integration): JsonResponse
    {
        $this->denyUnlessCapability('tenants.manage');
        abort_unless($integration->business_id === $tenant->id, 404);

        $result = TenantIntegrationService::probe($integration);

        AuditLog::record('tenant_integration_probed', $integration, [
            'summary' => "Health check ({$result['check']}) for integration {$integration->name} on {$tenant->name}: "
                .$result['status'].' — '.$result['detail']
                .' Acting platform user: '.$this->actor()->email.'.',
        ], $tenant->id);

        return $this->ok([
            ...$this->payload($tenant),
            'integration' => TenantIntegrationService::shape($integration->fresh()),
            'probe' => $result,
        ]);
    }

    public function destroy(Business $tenant, TenantIntegration $integration): JsonResponse
    {
        $this->denyUnlessCapability('tenants.manage');
        abort_unless($integration->business_id === $tenant->id, 404);

        $name = $integration->name;
        $type = $integration->type;
        $integration->delete();

        AuditLog::record('tenant_integration_deleted', $tenant, [
            'summary' => "Integration {$name} ({$type}) deleted from {$tenant->name}."
                .' Acting platform user: '.$this->actor()->email.'.',
        ], $tenant->id);

        return $this->ok($this->payload($tenant));
    }

    // ==================== internals ====================

    private function payload(Business $tenant): array
    {
        $integrations = TenantIntegration::where('business_id', $tenant->id)
            ->orderBy('type')->orderBy('name')->get();

        return [
            'catalog' => TenantIntegrationService::catalogPayload(),
            'entitlements' => collect(TenantIntegrationService::catalog())
                ->map(fn ($def) => (string) $def['feature'])
                ->unique()
                ->mapWithKeys(fn ($feature) => [$feature => FeatureResolver::enabled($tenant, $feature)])
                ->all(),
            'integrations' => $integrations->map(fn ($i) => TenantIntegrationService::shape($i))->all(),
        ];
    }

    /** Only keys the type declares as credentials are ever stored. */
    /**
     * Keep only the secret keys this type DECLARES.
     *
     * "Declares" means required OR optional: a type may accept a credential it
     * does not insist on (a self-hosted dictation engine usually runs unauthenticated
     * inside the clinic's network, but sits behind a gateway token in some
     * deployments). Filtering on the required list alone silently discarded an
     * optional secret the console had just offered a field for — the integration
     * saved as "active", then failed at call time with the gateway's 401 and no
     * hint why. Undeclared keys are still dropped.
     */
    private function filterSecretKeys(string $type, array $secrets): array
    {
        $allowed = TenantIntegrationService::allSecretKeys($type);
        $filtered = [];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $secrets) && $secrets[$key] !== null && $secrets[$key] !== '') {
                $filtered[$key] = (string) $secrets[$key];
            }
        }

        return $filtered;
    }

    private function assertFacilityBelongsToTenant(Business $tenant, ?int $facilityId): void
    {
        if ($facilityId === null) {
            return;
        }

        abort_unless(
            Location::where('id', $facilityId)->where('business_id', $tenant->id)->exists(),
            422,
            'That facility does not belong to this tenant.'
        );
    }

    private function denyUnlessFeature(Business $tenant, string $type): void
    {
        $feature = TenantIntegrationService::feature($type);

        abort_unless(
            $feature && FeatureResolver::enabled($tenant, $feature),
            403,
            "The [{$feature}] entitlement is not enabled for this tenant."
        );
    }
}
