<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SaaS control plane: platform roles, tenant memberships, lifecycle events,
 * feature overrides, usage counters, break-glass support sessions and audit
 * tenant attribution. Backfills memberships + lifecycle history for every
 * existing tenant/user so legacy single-tenant rows become first-class
 * tenant records without data loss (expand phase — nothing is dropped).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('platform_role')->nullable()->after('type');
            $table->index('type');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('max_locations')->nullable()->after('max_studies_per_month');
            $table->unsignedInteger('max_storage_mb')->nullable()->after('max_locations');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->timestamp('offboarded_at')->nullable()->after('subscription_ends_at');
            $table->timestamp('terminated_at')->nullable()->after('offboarded_at');
            $table->timestamp('data_retention_until')->nullable()->after('terminated_at');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('business_id')->nullable()->after('user_id');
            $table->index('business_id');
        });

        Schema::create('tenant_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('role')->default('admin');
            $table->boolean('is_default')->default(false);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['user_id', 'business_id']);
        });

        Schema::create('tenant_lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('event');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['business_id', 'created_at']);
        });

        Schema::create('tenant_feature_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('feature');
            $table->boolean('enabled');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'feature']);
        });

        Schema::create('usage_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('metric');
            $table->string('period', 7);
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->unique(['business_id', 'metric', 'period']);
        });

        Schema::create('support_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('reason');
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('created_ip')->nullable();
            $table->timestamps();
            $table->index(['platform_user_id', 'ended_at']);
        });

        $this->backfillMemberships();
        $this->backfillLifecycleHistory();
        $this->backfillUsageBaseline();
    }

    public function down(): void
    {
        Schema::dropIfExists('support_sessions');
        Schema::dropIfExists('usage_counters');
        Schema::dropIfExists('tenant_feature_overrides');
        Schema::dropIfExists('tenant_lifecycle_events');
        Schema::dropIfExists('tenant_memberships');

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['business_id']);
            $table->dropColumn('business_id');
        });
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['offboarded_at', 'terminated_at', 'data_retention_until']);
        });
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['max_locations', 'max_storage_mb']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('platform_role');
        });
    }

    /** Every staff user becomes a member of the tenant they already belong to. */
    private function backfillMemberships(): void
    {
        $staff = DB::table('users')
            ->where('business_id', '>', 0)
            ->whereNotIn('type', ['customer', 'super_admin', 'platform_admin'])
            ->get(['id', 'business_id', 'type']);

        foreach ($staff as $user) {
            $roleName = $user->type === 'admin' ? 'admin' : $this->dominantRoleName((int) $user->id, (int) $user->business_id);

            DB::table('tenant_memberships')->updateOrInsert(
                ['user_id' => $user->id, 'business_id' => $user->business_id],
                ['role' => $roleName, 'is_default' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    /** SPA-vocabulary role this user holds inside the given tenant, if any. */
    private function dominantRoleName(int $userId, int $businessId): string
    {
        $adminIds = DB::table('users')->where('business_id', $businessId)->whereIn('type', ['admin', 'super_admin'])->pluck('id');

        $roleName = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $userId)
            ->whereIn('roles.created_by', $adminIds)
            ->orderByRaw("CASE roles.name WHEN 'admin' THEN 0 WHEN 'radiologist' THEN 1 WHEN 'technician' THEN 2 WHEN 'receptionist' THEN 3 WHEN 'billing' THEN 4 ELSE 5 END")
            ->value('roles.name');

        return $roleName ?: 'receptionist';
    }

    private function backfillLifecycleHistory(): void
    {
        foreach (DB::table('businesses')->get(['id', 'subscription_status']) as $business) {
            DB::table('tenant_lifecycle_events')->insert([
                'business_id' => $business->id,
                'event' => 'tenant_imported',
                'from_status' => null,
                'to_status' => $business->subscription_status,
                'actor_id' => 0,
                'details' => json_encode(['summary' => 'Existing tenant imported into the control plane registry.']),
                'created_at' => now(),
            ]);
        }
    }

    /** Seed the current-period counters from real, persisted domain data. */
    private function backfillUsageBaseline(): void
    {
        $period = now()->format('Y-m');

        foreach (DB::table('businesses')->pluck('id') as $businessId) {
            $studies = DB::table('appointments')->where('business_id', $businessId)->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count();
            $reports = DB::table('radiology_reports')->whereIn('appointment_id', DB::table('appointments')->where('business_id', $businessId)->select('id'))->count();

            foreach (['studies' => $studies, 'reports' => $reports] as $metric => $value) {
                if ($value > 0) {
                    DB::table('usage_counters')->updateOrInsert(
                        ['business_id' => $businessId, 'metric' => $metric, 'period' => $period],
                        ['value' => $value, 'updated_at' => now()]
                    );
                }
            }
        }
    }
};
