<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\FailedJob;
use App\Models\TenantLifecycleEvent;
use App\Services\EntitlementReconciler;
use App\Services\JobInspector;
use App\Services\TenantHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform operations: observability (§80) and safe operational tooling (§81).
 *
 * Design rules this surface follows:
 *
 *  1. Every number is a real measurement. Nothing here is estimated, sampled
 *     or invented, and when a figure cannot be measured cheaply the payload
 *     says so instead of reporting a comfortable zero.
 *  2. A failed job's payload may contain patient data, so it is never
 *     returned — only the job class, its exception and the property NAMES of
 *     the job (see JobInspector).
 *  3. Anything that changes state is capability-gated AND audited, including
 *     the operator's stated reason where one is supplied.
 */
class PlatformOperationsController extends PlatformController
{
    private const JOB_PAGE_DEFAULT = 25;

    private const JOB_PAGE_MAX = 100;

    /** §80 — the observability dashboard. */
    public function index(): JsonResponse
    {
        $this->denyUnlessCapability('operations.manage');

        return $this->ok([
            'system' => $this->system(),
            ...TenantHealthService::dashboard(),
        ]);
    }

    /** §81 — inspect job state (payloads are never exposed). */
    public function jobs(Request $request): JsonResponse
    {
        $this->denyUnlessCapability('operations.manage');

        $limit = (int) $request->query('limit', self::JOB_PAGE_DEFAULT);
        $limit = max(1, min($limit ?: self::JOB_PAGE_DEFAULT, self::JOB_PAGE_MAX));

        return $this->ok([
            'jobs' => JobInspector::list($limit),
            'limit' => $limit,
            'system' => $this->system(),
        ]);
    }

    /** §81 — retry a failed background job, for real. */
    public function retryJob(string $uuid): JsonResponse
    {
        $this->denyUnlessCapability('operations.manage');

        $job = FailedJob::query()->where('uuid', $uuid)->first();
        abort_unless($job, 404, 'That failed job no longer exists.');

        // The database-uuids failer is addressed by UUID, not by numeric id.
        $exitCode = Artisan::call('queue:retry', ['id' => [$job->uuid]]);
        $output = trim(Artisan::output());

        $requeued = ! FailedJob::query()->where('uuid', $uuid)->exists();

        AuditLog::record('failed_job_retried', $job, [
            'summary' => "Failed job {$job->uuid} on queue [{$job->queue}] retried: "
                .($requeued ? 'pushed back onto the queue.' : 'the job is still recorded as failed.')
                .' Acting platform user: '.$this->actor()->email.'.',
        ]);

        return $this->ok([
            'uuid' => $uuid,
            'requeued' => $requeued,
            'exitCode' => $exitCode,
            'output' => mb_substr($output, 0, 1000),
            'job' => $requeued ? null : JobInspector::shape($job->refresh()),
        ]);
    }

    /** §81 — discard a failed job that will never be retried. */
    public function forgetJob(string $uuid): JsonResponse
    {
        $this->denyUnlessCapability('operations.manage');

        $job = FailedJob::query()->where('uuid', $uuid)->first();
        abort_unless($job, 404, 'That failed job no longer exists.');

        Artisan::call('queue:forget', ['id' => $job->uuid]);

        $removed = ! FailedJob::query()->where('uuid', $uuid)->exists();
        abort_unless($removed, 500, 'The failed job could not be removed.');

        AuditLog::record('failed_job_forgotten', $job, [
            'summary' => "Failed job {$job->uuid} on queue [{$job->queue}] discarded by an operator."
                .' Acting platform user: '.$this->actor()->email.'.',
        ]);

        return $this->ok(['uuid' => $uuid, 'removed' => true]);
    }

    /**
     * §81 — reconcile a tenant's entitlements against the plan and the
     * catalog. Reports by default; `apply` prunes only the inert overrides.
     */
    public function reconcileEntitlements(Request $request, Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('operations.manage');

        $validated = $request->validate([
            'apply' => ['sometimes', 'boolean'],
        ]);

        $apply = (bool) ($validated['apply'] ?? false);

        $report = $apply
            ? EntitlementReconciler::apply($tenant)
            : EntitlementReconciler::report($tenant);

        if ($apply) {
            AuditLog::record('entitlements_reconciled', $tenant, [
                'summary' => "Entitlements reconciled for {$tenant->name}: "
                    .count($report['pruned']).' stale override(s) pruned, '
                    .count($report['redundant']).' redundant, '
                    .count($report['contradicting']).' contradicting the plan.'
                    .' Acting platform user: '.$this->actor()->email.'.',
            ], $tenant->id);
        }

        return $this->ok([...$report, 'applied' => $apply]);
    }

    // ==================== internals ====================

    /**
     * Live infrastructure facts an operator would otherwise have to shell into
     * the box to learn. Each probe is guarded so a missing table or an absent
     * driver degrades to a stated value rather than a 500.
     */
    private function system(): array
    {
        $database = 'ok';

        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $database = 'down';
        }

        $pendingJobs = 0;
        $oldestPendingAt = null;

        if (Schema::hasTable('jobs')) {
            $pendingJobs = (int) DB::table('jobs')->count();
            $oldest = DB::table('jobs')->min('available_at');
            $oldestPendingAt = $oldest ? now()->setTimestamp((int) $oldest)->toIso8601String() : null;
        }

        $lastLifecycle = TenantLifecycleEvent::query()->orderByDesc('id')->first();

        return [
            'database' => $database,
            'cacheStore' => config('cache.default'),
            'queueConnection' => config('queue.default'),
            'failedJobDriver' => config('queue.failed.driver'),
            'pendingJobs' => $pendingJobs,
            'oldestPendingJobAt' => $oldestPendingAt,
            'failedJobs' => Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0,
            'storageWritable' => is_writable(storage_path()),
            'appEnv' => app()->environment(),
            'appVersion' => config('ris.app_version'),
            'lastLifecycleEventAt' => $lastLifecycle?->created_at?->toIso8601String(),
            'lastLifecycleEvent' => $lastLifecycle?->event,
            'checkedAt' => now()->toIso8601String(),
        ];
    }
}
