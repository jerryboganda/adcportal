<?php

namespace App\Services;

use App\Http\Resources\ApiShape;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\SupportSession;
use App\Models\TenantLifecycleEvent;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The tenant lifecycle engine: provisioning, activation, suspension,
 * offboarding and termination. Every transition is transactional, recorded in
 * the tenant lifecycle registry, audited, and idempotent where a retry can
 * happen (provisioning). Terminated tenants retain their data until the
 * configured retention window passes — clinical data is never deleted
 * casually; destruction is an explicit operator command only.
 */
class TenantLifecycleService
{
    /**
     * Provision a brand-new tenant end-to-end: owner admin, tenant row,
     * membership, roles/masters bootstrap, then activation (or trial start).
     *
     * @param  array  $adminAttributes  Either ['user' => User] (existing user
     *                                  becomes owner) or full new-user attributes.
     * @return array{business: Business, admin: ?User, initialPassword: ?string}
     */
    public function provision(string $clinicName, array $adminAttributes, ?Plan $plan = null, ?string $planSlug = null, ?int $trialDays = null, ?string $subscriptionEndsAt = null, string $orgType = 'clinic'): array
    {
        $plan = $plan
            ?? Plan::where('slug', $planSlug ?? 'starter')->where('is_active', true)->first()
            ?? Plan::where('is_active', true)->orderBy('price_monthly')->first();

        $initialPassword = null;

        $business = DB::transaction(function () use ($clinicName, $adminAttributes, $plan, $trialDays, $subscriptionEndsAt, $orgType, &$initialPassword) {
            $existingOwner = $adminAttributes['user'] ?? null;

            if ($existingOwner instanceof User) {
                $admin = $existingOwner;
            } else {
                $initialPassword = $adminAttributes['password'] ?? Str::password(16);
                $admin = User::create([
                    ...$adminAttributes,
                    'password' => $initialPassword,
                    'type' => 'admin',
                    'active_status' => 1,
                    'lang' => 'en',
                    'email_verified_at' => $adminAttributes['email_verified_at'] ?? now(),
                    'initials' => $adminAttributes['initials'] ?? mb_strtoupper(mb_substr($adminAttributes['name'], 0, 1)),
                    'department' => $adminAttributes['department'] ?? 'Administration',
                ]);
            }

            $business = Business::create([
                'name' => $clinicName,
                // Clinic and hospital tenants share the portal; the flavor is
                // stored/audited so hospital features can key off it later.
                'org_type' => in_array($orgType, ['clinic', 'hospital'], true) ? $orgType : 'clinic',
                'form_type' => 'form-layout',
                'layouts' => 'Formlayout11',
                'theme_color' => 'color1-Formlayout11',
                'plan_id' => $plan?->id,
                'subscription_status' => 'provisioning',
                'trial_ends_at' => $subscriptionEndsAt ? null : now()->addDays($trialDays ?? $plan?->trial_days ?? 14),
                'subscription_ends_at' => $subscriptionEndsAt,
                'tenant_code' => strtoupper(Str::random(3)).'-'.random_int(1000, 9999),
                'created_by' => $admin->id,
                // Every tenant starts on the operator's declared default placement;
                // moving it later is an explicit, audited control-plane action.
                'region' => array_key_first(config('ris.regions', ['default' => []])) ?: 'default',
                'deployment_stamp' => array_key_first(config('ris.deployment_stamps', ['stamp-a' => []])) ?: 'stamp-a',
                'isolation_profile' => 'pooled',
                'database_cluster' => 'primary',
                'storage_region' => array_key_first(config('ris.regions', ['default' => []])) ?: 'default',
            ]);

            $admin->forceFill([
                'active_business' => $business->id,
                'business_id' => $business->id,
                'created_by' => $business->id,
            ])->save();

            TenantMembership::create([
                'user_id' => $admin->id,
                'business_id' => $business->id,
                'role' => 'admin',
                'is_default' => true,
                'status' => 'active',
            ]);

            TenantLifecycleEvent::record($business, 'tenant_created', null, 'provisioning', ['summary' => 'Tenant created on plan '.($plan?->name ?? 'none').'.']);

            // Tenant-scoped role + permission wiring (laratrust, per-clinic).
            $admin->MakeRole();
            app(\Database\Seeders\TenantBootstrap::class)->run($business, $admin);

            TenantLifecycleEvent::record($business, 'provisioned', 'provisioning', 'provisioning', ['summary' => 'Roles, masters, screening forms, report templates and notification templates provisioned.']);

            $target = $subscriptionEndsAt ? 'active' : 'trialing';
            $business->forceFill(['subscription_status' => $target])->save();
            TenantLifecycleEvent::record($business, 'activated', 'provisioning', $target, ['summary' => $target === 'trialing' ? 'Trial started.' : 'Subscription activated.']);

            return $business;
        });

        return [
            'business' => $business,
            'admin' => User::find($business->created_by),
            'initialPassword' => $initialPassword,
        ];
    }

    /** Idempotent recovery for a tenant stuck in provisioning. */
    public function retryProvisioning(Business $tenant): Business
    {
        abort_unless($tenant->subscription_status === 'provisioning', 422, 'Tenant is not awaiting provisioning.');

        DB::transaction(function () use ($tenant) {
            $admin = User::find($tenant->created_by);

            if ($admin) {
                $admin->MakeRole();
                app(\Database\Seeders\TenantBootstrap::class)->run($tenant, $admin);

                TenantMembership::firstOrCreate(
                    ['user_id' => $admin->id, 'business_id' => $tenant->id],
                    ['role' => 'admin', 'is_default' => true, 'status' => 'active']
                );

                if (method_exists($admin, 'flushCache')) {
                    $admin->flushCache();
                }
            }

            $target = $tenant->subscription_ends_at ? 'active' : 'trialing';
            $from = $tenant->subscription_status;
            $tenant->forceFill(['subscription_status' => $target])->save();

            TenantLifecycleEvent::record($tenant, 'provisioning_retried', $from, $target, ['summary' => 'Provisioning re-run completed safely.']);
        });

        return $tenant->fresh();
    }

    public function activate(Business $tenant, string $reason = ''): Business
    {
        return $this->transition($tenant, 'active', 'tenant_activated', $reason);
    }

    public function suspend(Business $tenant, string $reason = ''): Business
    {
        return $this->transition($tenant, 'suspended', 'tenant_suspended', $reason);
    }

    public function reactivate(Business $tenant, string $reason = ''): Business
    {
        return $this->transition($tenant, 'active', 'tenant_reactivated', $reason);
    }

    /** Offboarding: revoke access, snapshot a data export, start the retention clock. */
    public function startOffboarding(Business $tenant, string $reason = ''): Business
    {
        $from = $tenant->subscription_status;

        if ($from === 'offboarding') {
            return $tenant;
        }

        DB::transaction(function () use ($tenant, $reason, $from) {
            $tenant->forceFill([
                'subscription_status' => 'offboarding',
                'offboarded_at' => now(),
                'data_retention_until' => now()->addDays((int) config('ris.retention_days', 90)),
            ])->save();

            $this->revokeTenantLogins($tenant);

            TenantLifecycleEvent::record($tenant, 'offboarding_started', $from, 'offboarding', [
                'summary' => $reason !== '' ? $reason : 'Offboarding started; data export captured and access revoked.',
                'exportPath' => $this->captureDataExport($tenant),
            ]);
        });

        $this->auditTenant($tenant, 'tenant_offboarding_started', $reason);

        return $tenant->fresh();
    }

    /**
     * Termination is the policy-controlled end of the tenant: all access is
     * revoked, active support sessions are closed, and retained data gains a
     * destruction deadline. Retained clinical data is destroyed only by the
     * explicit `ris:tenant-destroy` operator command after the retention
     * window — never automatically, never casually.
     */
    public function terminate(Business $tenant, string $reason = ''): Business
    {
        if ($tenant->subscription_status !== 'offboarding') {
            $this->startOffboarding($tenant, $reason);
            $tenant->refresh();
        }

        DB::transaction(function () use ($tenant, $reason) {
            SupportSession::where('business_id', $tenant->id)->whereNull('ended_at')->update(['ended_at' => now()]);

            $tenant->forceFill([
                'subscription_status' => 'terminated',
                'terminated_at' => now(),
            ])->save();

            TenantLifecycleEvent::record($tenant, 'tenant_terminated', 'offboarding', 'terminated', [
                'summary' => $reason !== '' ? $reason : 'Tenant terminated; data retained until '.$tenant->data_retention_until?->toDateString().'.',
            ]);
        });

        $this->auditTenant($tenant, 'tenant_terminated', $reason);

        return $tenant->fresh();
    }

    /** Scheduler sweep: lapses finished trials and subscription terms to `expired`. */
    public function sweepExpiries(): int
    {
        $lapsed = 0;

        Business::query()
            ->where('subscription_status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->each(function (Business $tenant) use (&$lapsed) {
                $this->transition($tenant, 'expired', 'subscription_expired', 'Trial ended.');
                $lapsed++;
            });

        Business::query()
            ->where('subscription_status', 'active')
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<', now())
            ->each(function (Business $tenant) use (&$lapsed) {
                $this->transition($tenant, 'expired', 'subscription_expired', 'Subscription term ended.');
                $lapsed++;
            });

        return $lapsed;
    }

    private function transition(Business $tenant, string $to, string $event, string $reason = ''): Business
    {
        $from = $tenant->subscription_status;

        if ($from === $to) {
            return $tenant;
        }

        DB::transaction(function () use ($tenant, $to, $event, $from, $reason) {
            $updates = ['subscription_status' => $to];

            // Re-activation with a lapsed term clears the stale end date; the
            // platform sets an explicit new term via the subscription update.
            if ($to === 'active' && ($tenant->subscription_ends_at && $tenant->subscription_ends_at->isPast())) {
                $updates['subscription_ends_at'] = null;
            }

            if ($to === 'offboarding') {
                $updates['offboarded_at'] = now();
                $updates['data_retention_until'] = now()->addDays((int) config('ris.retention_days', 90));
            }

            if ($to === 'terminated') {
                $updates['terminated_at'] = now();
            }

            $tenant->forceFill($updates)->save();

            if (in_array($to, ['suspended', 'expired', 'offboarding', 'terminated'], true)) {
                // While not subscribable the tenant keeps its data but every
                // tenant API request is refused by EnsureTenantActive.
                $this->revokeTenantLogins($tenant);
            }

            TenantLifecycleEvent::record($tenant, $event, $from, $to, ['summary' => $reason !== '' ? $reason : "Status changed {$from} → {$to}."]);
        });

        $this->auditTenant($tenant, $event, $reason);

        return $tenant->fresh();
    }

    private function revokeTenantLogins(Business $tenant): void
    {
        User::where('business_id', $tenant->id)->whereIn('type', ['admin', 'staff'])->update(['is_enable_login' => 0]);
    }

    /** Capture a full tenant data export into platform storage for the retention file. */
    private function captureDataExport(Business $tenant): string
    {
        $tenantId = $tenant->id;

        $payload = [
            'exportedAt' => now()->toIso8601String(),
            'system' => 'PolytronX - RIS Portal',
            'tenant' => ApiShape::tenant($tenant),
            'data' => [
                'patients' => Customer::where('business_id', $tenantId)->get()->map(fn ($c) => ApiShape::patient($c))->all(),
                'studies' => \App\Models\Appointment::forClinic($tenantId)->get()->map(fn ($a) => ApiShape::appointment($a->load(\App\Http\Controllers\Api\V1\StudyController::eager())))->all(),
                'invoices' => Invoice::forClinic($tenantId)->with(['items', 'payments'])->get()->map(fn ($i) => ApiShape::invoice($i))->all(),
                'clinicSettings' => ApiShape::clinicSettings($tenantId),
            ],
        ];

        $path = "tenant-exports/tenant_{$tenantId}_".now()->format('Ymd_His').'.json';
        Storage::disk('local')->put($path, json_encode($payload, JSON_PRETTY_PRINT));

        return $path;
    }

    private function auditTenant(Business $tenant, string $action, string $reason = ''): void
    {
        AuditLog::record($action, $tenant, [
            'summary' => "Tenant {$tenant->name} ({$tenant->tenant_code}): ".($reason !== '' ? $reason : "now {$tenant->subscription_status}."),
        ], $tenant->id);
    }
}
