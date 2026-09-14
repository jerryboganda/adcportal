<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\AdverseReaction;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use App\Models\DicomNode;
use App\Models\DoctorDispatchLog;
use App\Models\DoseLog;
use App\Models\InventoryTransaction;
use App\Models\Invoice;
use App\Models\RadiologyReport;
use App\Models\ReportRelease;
use App\Models\Role;
use App\Models\StudyScreeningAnswer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeDemoTenant extends Command
{
    /**
     * Tables that carry a business_id but are handled explicitly (or are
     * global) and must be skipped by the generic sweep.
     */
    protected array $sweepExclusions = [
        'businesses',
        'users',
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'password_reset_tokens',
    ];

    protected $signature = 'ris:purge-demo
                            {--force : Delete without asking for confirmation}';

    protected $description = 'Delete the demo tenant (RIS_DEMO_TENANT_CODE) and every record belonging to it. Refuses to touch any other tenant.';

    public function handle(): int
    {
        $demoCode = config('ris.demo_tenant_code');
        $business = Business::where('tenant_code', $demoCode)->first();

        if (! $business) {
            $this->info("No demo tenant found (tenant_code={$demoCode}). Nothing to purge.");

            return 0;
        }

        $this->warn("About to delete demo tenant #{$business->id} \"{$business->name}\" (tenant_code={$business->tenant_code}) and ALL of its data.");

        $counts = [
            'customers' => Customer::where('business_id', $business->id)->count(),
            'appointments' => Appointment::withTrashed()->where('business_id', $business->id)->count(),
            'invoices' => Invoice::withTrashed()->where('business_id', $business->id)->count(),
            'reports' => RadiologyReport::withTrashed()->whereIn('appointment_id', fn ($q) => $q->select('id')->from('appointments')->where('business_id', $business->id))->count(),
        ];
        foreach ($counts as $label => $count) {
            $this->line("  {$label}: {$count}");
        }

        if (! $this->option('force') && ! $this->confirm('Permanently delete this tenant and its data?')) {
            $this->info('Aborted.');

            return 0;
        }

        $tenantId = $business->id;
        $demoAdminEmail = config('ris.demo_admin_email');

        DB::transaction(function () use ($tenantId, $demoCode, $demoAdminEmail) {
            $appointmentIds = Appointment::withTrashed()->where('business_id', $tenantId)->pluck('id');
            $invoiceIds = Invoice::withTrashed()->where('business_id', $tenantId)->pluck('id');
            $reportIds = RadiologyReport::withTrashed()->whereIn('appointment_id', $appointmentIds)->pluck('id');

            // Clinical + billing children first (mirrors SettingsController::resetDemo).
            \App\Models\InvoiceItem::whereIn('invoice_id', $invoiceIds)->delete();
            \App\Models\InvoicePayment::whereIn('invoice_id', $invoiceIds)->delete();
            Invoice::withTrashed()->whereIn('id', $invoiceIds)->forceDelete();
            ReportRelease::whereIn('report_id', $reportIds)->delete();
            RadiologyReport::withTrashed()->whereIn('id', $reportIds)->forceDelete();
            StudyScreeningAnswer::whereIn('appointment_id', $appointmentIds)->delete();
            DoseLog::whereIn('appointment_id', $appointmentIds)->delete();
            \App\Models\AppointmentProcedure::whereIn('appointment_id', $appointmentIds)->delete();
            Appointment::withTrashed()->whereIn('id', $appointmentIds)->forceDelete();

            AppNotification::where('business_id', $tenantId)->delete();
            DoctorDispatchLog::where('business_id', $tenantId)->delete();
            InventoryTransaction::where('business_id', $tenantId)->delete();
            AdverseReaction::where('business_id', $tenantId)->delete();
            Customer::where('business_id', $tenantId)->delete();
            DicomNode::where('business_id', $tenantId)->delete();

            // Users: demo admin + any staff scoped to the tenant.
            $userIds = User::where(function ($q) use ($tenantId, $demoAdminEmail) {
                $q->where('business_id', $tenantId)
                    ->orWhere('active_business', $tenantId);
                if ($demoAdminEmail) {
                    $q->orWhere('email', $demoAdminEmail);
                }
            })->pluck('id');

            // Tenant-owned settings use the `business` column (not business_id).
            // Also drop platform-scoped rows (business = 0) that the demo admin
            // created — re-seeding settings under that admin duplicates keys.
            DB::table('settings')->where('business', $tenantId)->delete();
            if ($userIds->isNotEmpty()) {
                DB::table('settings')->where('business', 0)->whereIn('created_by', $userIds)->delete();
            }

            if ($userIds->isNotEmpty()) {
                DB::table('role_user')->whereIn('user_id', $userIds)->delete();
                User::whereIn('id', $userIds)->delete();
            }

            // Roles are per-tenant, keyed by created_by = tenant admin id.
            $roleIds = Role::where(function ($q) use ($tenantId, $userIds) {
                $q->whereIn('created_by', $userIds)->orWhere('created_by', $tenantId);
            })->pluck('id');
            if ($roleIds->isNotEmpty()) {
                DB::table('permission_role')->whereIn('role_id', $roleIds)->delete();
                Role::whereIn('id', $roleIds)->delete();
            }

            // Generic sweep: every remaining table with a business_id column.
            foreach (Schema::getTables() as $table) {
                $name = is_array($table) ? ($table['name'] ?? '') : ($table->name ?? '');
                if ($name === '' || in_array($name, $this->sweepExclusions, true)) {
                    continue;
                }
                if (! Schema::hasColumn($name, 'business_id')) {
                    continue;
                }
                DB::table($name)->where('business_id', $tenantId)->delete();
            }

            Business::where('id', $tenantId)->where('tenant_code', $demoCode)->delete();
        });

        Cache::flush();

        $this->info("Demo tenant #{$tenantId} purged. Cache flushed.");

        return 0;
    }
}
