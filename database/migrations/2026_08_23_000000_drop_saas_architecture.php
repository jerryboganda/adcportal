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
     */
    public function up(): void
    {
        // 1. Drop SaaS tables (order matters for FKs).
        Schema::disableForeignKeyConstraints();

        foreach ([
            'user_coupons',
            'coupons',
            'orders',
            'user_active_modules',
            'add_ons',
            'plans',
            'bank_transfer_payments',
            'subscription_items',
            'subscriptions',
            'receipts',
            'transactions',
        ] as $table) {
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
