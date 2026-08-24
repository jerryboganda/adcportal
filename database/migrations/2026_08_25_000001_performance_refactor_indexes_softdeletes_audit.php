<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance refactor: composite indexes on hot query paths,
 * unique business slugs, soft deletes, and the audit log table.
 *
 * Note: appointment `date`/`time` remain strings ('d-m-Y' / 'H:i') by design —
 * every query in the codebase is equality-based (never range), which indexes
 * serve perfectly. A DATE/TIME conversion would break 40+ call sites for
 * negligible gain.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Appointments: hot paths (lists, slot availability, dashboard) ----
        Schema::table('appointments', function (Blueprint $table) {
            $indexExists = collect(DB::select("PRAGMA index_list('appointments')"))->pluck('name')->toArray();
            $names = method_exists($table, 'getIndexConfig') ? [] : $indexExists;

            if (! in_array('appointments_business_created_date_index', $names)) {
                $table->index(['business_id', 'created_by', 'date'], 'appointments_business_created_date_index');
            }
            if (! in_array('appointments_staff_date_index', $names)) {
                $table->index(['staff_id', 'date', 'time'], 'appointments_staff_date_index');
            }
            if (! in_array('appointments_service_date_index', $names)) {
                $table->index(['service_id', 'date'], 'appointments_service_date_index');
            }
            if (! in_array('appointments_customer_index', $names)) {
                $table->index('customer_id', 'appointments_customer_index');
            }
            if (! in_array('appointments_status_index', $names)) {
                $table->index('appointment_status', 'appointments_status_index');
            }
        });

        // ---- Services / Locations / Staff / Users: clinic-scoped lookups ----
        Schema::table('services', function (Blueprint $table) {
            $table->index(['business_id', 'created_by'], 'services_business_created_index');
        });
        Schema::table('locations', function (Blueprint $table) {
            $table->index(['business_id', 'created_by'], 'locations_business_created_index');
        });
        Schema::table('staff', function (Blueprint $table) {
            $table->index(['business_id', 'created_by'], 'staff_business_created_index');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->index(['type', 'business_id', 'created_by'], 'users_type_business_created_index');
        });

        // ---- Unique business slugs ----
        try {
            Schema::table('businesses', function (Blueprint $table) {
                $table->unique('slug', 'businesses_slug_unique');
            });
        } catch (Throwable $e) {
            // Duplicate slugs may pre-exist; de-duplicate first.
            $rows = DB::table('businesses')->select('id', 'slug')->orderBy('id')->get();
            $seen = [];
            foreach ($rows as $row) {
                if (isset($seen[$row->slug])) {
                    DB::table('businesses')->where('id', $row->id)->update([
                        'slug' => $row->slug.'-'.$row->id,
                    ]);
                }
                $seen[$row->slug] = true;
            }
            Schema::table('businesses', function (Blueprint $table) {
                $table->unique('slug', 'businesses_slug_unique');
            });
        }

        // ---- Soft deletes ----
        Schema::table('appointments', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
        Schema::table('services', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });

        // ---- Audit log ----
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('action', 64)->index();          // created|updated|status_changed|deleted
                $table->string('subject_type', 64);             // Appointment etc.
                $table->unsignedBigInteger('subject_id')->index();
                $table->json('changes')->nullable();            // old/new diff
                $table->string('ip', 45)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_business_created_date_index');
            $table->dropIndex('appointments_staff_date_index');
            $table->dropIndex('appointments_service_date_index');
            $table->dropIndex('appointments_customer_index');
            $table->dropIndex('appointments_status_index');
            $table->dropSoftDeletes();
        });
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex('services_business_created_index');
            $table->dropSoftDeletes();
        });
        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex('locations_business_created_index');
        });
        Schema::table('staff', function (Blueprint $table) {
            $table->dropIndex('staff_business_created_index');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_type_business_created_index');
        });
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropUnique('businesses_slug_unique');
        });
        Schema::dropIfExists('audit_logs');
    }
};
