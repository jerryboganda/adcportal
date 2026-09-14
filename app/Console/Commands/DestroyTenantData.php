<?php

namespace App\Console\Commands;

use App\Models\Business;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Explicit, operator-driven destruction of a TERMINATED tenant's retained
 * data after (or, with --force, before) its retention deadline. Deliberately
 * NOT reachable from any API — healthcare data is never deleted casually.
 */
class DestroyTenantData extends Command
{
    protected $signature = 'ris:tenant-destroy {tenant_code} {--confirm= : Must equal the tenant code} {--force : Destroy even before the retention deadline}';

    protected $description = 'Destroy all retained data of a terminated tenant (explicit confirmation required)';

    public function handle(): int
    {
        $tenant = Business::where('tenant_code', $this->argument('tenant_code'))->first();

        if (! $tenant) {
            $this->error('No tenant with that tenant_code exists.');

            return self::FAILURE;
        }

        if ($tenant->subscription_status !== 'terminated') {
            $this->error('Only terminated tenants can be destroyed. Run the offboarding/terminate lifecycle first.');

            return self::FAILURE;
        }

        if ($this->option('confirm') !== $tenant->tenant_code) {
            $this->error('--confirm must equal the tenant code ('.$tenant->tenant_code.').');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            if (! $tenant->data_retention_until) {
                $this->error('Tenant has no retention deadline recorded; re-run with --force if you are certain.');

                return self::FAILURE;
            }
            if ($tenant->data_retention_until->isFuture()) {
                $this->error('Retention window runs until '.$tenant->data_retention_until->toDateString().' — use --force to override.');

                return self::FAILURE;
            }
        }

        if (! $this->confirm("PERMANENTLY destroy tenant '{$tenant->name}' and every related row? This cannot be undone.")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        // Audit trail survives the tenant (audit_logs.business_id is not an FK).
        \App\Models\AuditLog::record('tenant_data_destroyed', $tenant, [
            'summary' => "Retained data of tenant {$tenant->name} ({$tenant->tenant_code}) destroyed by operator command.",
        ]);

        $tableMap = $this->businessScopedTables();

        DB::transaction(function () use ($tenant, $tableMap) {
            $destroyed = 0;
            foreach ($tableMap as $table) {
                $destroyed += DB::table($table)->where('business_id', $tenant->id)->delete();
            }

            // Platform rows that reference the tenant without business_id.
            DB::table('tenant_memberships')->where('business_id', $tenant->id)->delete();
            DB::table('tenant_feature_overrides')->where('business_id', $tenant->id)->delete();
            DB::table('usage_counters')->where('business_id', $tenant->id)->delete();
            DB::table('support_sessions')->where('business_id', $tenant->id)->delete();

            // Tenant-scoped users (staff belong to exactly this tenant).
            $destroyed += DB::table('users')->where('business_id', $tenant->id)->where('type', '!=', 'super_admin')->delete();

            $tenant->delete();

            $this->line("Destroyed {$destroyed} rows across ".count($tableMap).' tables.');
        });

        $this->info("Tenant {$tenant->tenant_code} destroyed.");

        return self::SUCCESS;
    }

    /** Every table carrying a business_id column — the pooled-tenancy sweep. */
    private function businessScopedTables(): array
    {
        $tables = Schema::getTables();

        $scoped = [];
        foreach ($tables as $table) {
            $name = $table['name'] ?? null;
            if (! $name || in_array($name, ['migrations'], true)) {
                continue;
            }
            if (Schema::hasColumn($name, 'business_id')) {
                $scoped[] = $name;
            }
        }

        return $scoped;
    }
}
