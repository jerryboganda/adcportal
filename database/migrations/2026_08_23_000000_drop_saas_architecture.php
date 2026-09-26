<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-clinic conversion: drop the SaaS / multi-tenant architecture
     * from an existing database.
     *
     * - Drops SaaS tables (plans, orders, coupons, add-ons, modules, Paddle billing, bank transfers)
     * - Removes plan/SaaS columns from users
     * - Deletes non-admin users (super admin / company) after re-pointing ownership to the admin
     *
     * DESTRUCTIVE, AND IRREVERSIBLE — it drops tables and DELETES USER ROWS with
     * no `down`. That is fine on a fresh install, where none of the legacy tables
     * exist and the whole method is a no-op.
     *
     * It is not fine when a legacy database is restored from a pre-August backup
     * and then migrated: `php artisan migrate` runs this automatically on every
     * container start, so restoring a backup silently destroys the billing tables
     * and every staff account that was not the first `company` user.
     *
     * So the destructive path now requires `RIS_ALLOW_DESTRUCTIVE_SAAS_DROP=true`
     * and ABORTS LOUDLY when legacy tables are present without it, rather than
     * proceeding. An operator gets a clear instruction instead of a gutted
     * database.
     */
    private const LEGACY_TABLES = [
        'user_coupons', 'coupons', 'orders', 'user_active_modules', 'add_ons',
        'plans', 'bank_transfer_payments', 'subscription_items', 'subscriptions',
        'receipts', 'transactions',
    ];

    public function up(): void
    {
        $present = array_values(array_filter(
            self::LEGACY_TABLES,
            fn (string $table) => Schema::hasTable($table)
        ));

        // Fresh install: nothing to convert. This is the normal case.
        if ($present === []) {
            return;
        }

        if (! env('RIS_ALLOW_DESTRUCTIVE_SAAS_DROP', false)) {
            throw new RuntimeException(
                'This database still carries the legacy single-clinic tables: '.implode(', ', $present).'. '
                .'Converting it DROPS those tables and DELETES non-admin user rows, irreversibly. '
                .'Take a backup first, then re-run with RIS_ALLOW_DESTRUCTIVE_SAAS_DROP=true to confirm.'
            );
        }

        // 1. Drop SaaS tables (order matters for FKs).
        Schema::disableForeignKeyConstraints();

        foreach (self::LEGACY_TABLES as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();

        // 2. Remove SaaS columns from users (if present — older DBs may not have them all).
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'requested_plan',
                'active_plan',
                'billing_type',
                'active_module',
                'plan_expire_date',
                'trial_expire_date',
                'is_trial_done',
                'total_user',
                'total_business',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        // 3. Re-point data ownership: map the first 'company' user to type 'admin'
        //    and delete remaining super admin / company users.
        if (Schema::hasTable('users')) {
            $company = \DB::table('users')->where('type', 'company')->orderBy('id')->first();

            if ($company) {
                // Promote the first company user to admin.
                \DB::table('users')->where('id', $company->id)->update(['type' => 'admin']);

                // Re-point everything owned by other company/super-admin users to this admin.
                $adminId = $company->id;

                \DB::table('users')
                    ->whereIn('type', ['super admin', 'company'])
                    ->where('id', '!=', $adminId)
                    ->orderBy('id')
                    ->each(function ($user) use ($adminId) {
                        // Move owned records to the admin.
                        foreach (['created_by'] as $col) {
                            \DB::table('businesses')->where($col, $user->id)->update([$col => $adminId]);
                            \DB::table('settings')->where($col, $user->id)->update([$col => $adminId]);
                        }
                        // Delete the user's role bindings and the user itself.
                        \DB::table('role_user')->where('user_id', $user->id)->delete();
                        \DB::table('users')->where('id', $user->id)->delete();
                    });
            }

            // Any leftover super admins without a company owner get deleted too.
            \DB::table('role_user')
                ->whereIn('user_id', function ($q) {
                    $q->select('id')->from('users')->where('type', 'super admin');
                })
                ->delete();
            \DB::table('users')->where('type', 'super admin')->delete();
        }
    }

    public function down(): void
    {
        // Irreversible conversion — no down path.
    }
};
