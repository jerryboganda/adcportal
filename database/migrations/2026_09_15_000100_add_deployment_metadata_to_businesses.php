<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant deployment / regionalization metadata (expand-only).
 *
 * The control plane records where each tenant's data actually lives so pooled,
 * isolated and dedicated deployments can be managed consistently: region,
 * deployment stamp, isolation profile, database cluster and storage region.
 * Existing tenants are backfilled to the pooled default declared in
 * config/ris.php — no row is left without an explicit placement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('region')->nullable()->after('data_retention_until');
            $table->string('deployment_stamp')->nullable()->after('region');
            $table->string('isolation_profile')->nullable()->after('deployment_stamp');
            $table->string('database_cluster')->nullable()->after('isolation_profile');
            $table->string('storage_region')->nullable()->after('database_cluster');
            $table->index(['region', 'subscription_status']);
        });

        $defaultRegion = array_key_first(config('ris.regions', ['default' => []])) ?: 'default';
        $defaultStamp = array_key_first(config('ris.deployment_stamps', ['stamp-a' => []])) ?: 'stamp-a';

        DB::table('businesses')->whereNull('isolation_profile')->update([
            'region' => $defaultRegion,
            'deployment_stamp' => $defaultStamp,
            'isolation_profile' => 'pooled',
            'database_cluster' => 'primary',
            'storage_region' => $defaultRegion,
        ]);
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex(['region', 'subscription_status']);
            $table->dropColumn(['region', 'deployment_stamp', 'isolation_profile', 'database_cluster', 'storage_region']);
        });
    }
};
