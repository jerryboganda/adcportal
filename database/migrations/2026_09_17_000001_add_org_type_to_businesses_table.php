<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tenant organization flavor. Clinic and hospital tenants share the same
     * portal and permission model today — this only labels the tenant so
     * hospital-specific features can key off it later. Existing tenants
     * default to 'clinic'.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (!Schema::hasColumn('businesses', 'org_type')) {
                $table->string('org_type', 20)->default('clinic')->after('name');
                $table->index('org_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (Schema::hasColumn('businesses', 'org_type')) {
                $table->dropIndex(['org_type']);
                $table->dropColumn('org_type');
            }
        });
    }
};
