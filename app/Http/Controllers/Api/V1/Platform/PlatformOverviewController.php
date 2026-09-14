<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Plan;
use App\Models\SupportSession;
use App\Models\TenantLifecycleEvent;
use App\Models\User;
use App\Services\StorageMeter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * SaaS platform overview: real, persisted operational and commercial
 * telemetry across all tenants. Financial values are derived from actual
 * plan assignments only — nothing is invented.
 */
class PlatformOverviewController extends PlatformController
{
    public function index(): JsonResponse
    {
        $this->denyUnlessCapability('tenants.view');

        $period = now()->format('Y-m');

        $statusCounts = Business::query()
            ->select('subscription_status', DB::raw('count(*) as total'))
            ->groupBy('subscription_status')
            ->pluck('total', 'subscription_status');

        $status = fn (string $key): int => (int) ($statusCounts[$key] ?? 0);

        // Contracted monthly value: the plan price of every tenant currently
        // on an active subscription or trial (manual activation — no gateway).
        $contracted = (float) Business::query()
            ->whereIn('subscription_status', ['active', 'trialing'])
            ->join('plans', 'plans.id', '=', 'businesses.plan_id')
            ->sum('plans.price_monthly');

        $studiesThisMonth = (int) DB::table('usage_counters')->where('metric', 'studies')->where('period', $period)->sum('value');
        $reportsThisMonth = (int) DB::table('usage_counters')->where('metric', 'reports')->where('period', $period)->sum('value');

        // Live fallback when counters have not been seeded for this period.
        if ($studiesThisMonth === 0) {
            $studiesThisMonth = Appointment::whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count();
        }

        $storageBytes = (int) Business::pluck('id')->sum(fn ($id) => StorageMeter::bytes((int) $id));

        return $this->ok([
            'stats' => [
                'tenants' => [
                    'total' => array_sum($statusCounts->all()),
                    'active' => $status('active'),
                    'trialing' => $status('trialing'),
                    'suspended' => $status('suspended'),
                    'expired' => $status('expired'),
                    'provisioning' => $status('provisioning'),
                    'offboarding' => $status('offboarding'),
                    'terminated' => $status('terminated'),
                    'needsAttention' => $status('suspended') + $status('provisioning') + $status('expired'),
                ],
                'commercial' => [
                    'contractedMonthlyValue' => round($contracted, 2),
                    'currency' => Plan::query()->orderByDesc('id')->value('currency') ?? 'PKR',
                    'trialsExpiringIn7Days' => Business::query()
                        ->where('subscription_status', 'trialing')
                        ->whereNotNull('trial_ends_at')
                        ->whereBetween('trial_ends_at', [now(), now()->addDays(7)])
                        ->count(),
                    'newTenantsLast30Days' => Business::query()->where('created_at', '>=', now()->subDays(30))->count(),
                ],
                'usage' => [
                    'studiesThisMonth' => $studiesThisMonth,
                    'reportsThisMonth' => $reportsThisMonth,
                    'users' => User::whereNotIn('type', ['customer'])->count(),
                    'storageBytes' => $storageBytes,
                ],
                'operations' => [
                    'activeSupportSessions' => SupportSession::query()->whereNull('ended_at')->where('expires_at', '>', now())->count(),
                    'failedJobs' => DB::table('failed_jobs')->count(),
                    'recentLifecycleEvents' => TenantLifecycleEvent::query()->orderByDesc('id')->limit(8)
                        ->get(['id', 'business_id', 'event', 'to_status', 'created_at'])
                        ->map(fn ($e) => [
                            'id' => (string) $e->id,
                            'tenantId' => (string) $e->business_id,
                            'event' => $e->event,
                            'toStatus' => $e->to_status,
                            'at' => $e->created_at?->toIso8601String(),
                        ])->all(),
                ],
            ],
        ]);
    }
}
