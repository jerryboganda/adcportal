<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace staff.location_id / staff.service_id CSV strings with proper
     * pivot tables (CSV columns kept temporarily for rollback safety).
     */
    public function up(): void
    {
        Schema::create('staff_location', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id')->index();
            $table->unsignedBigInteger('location_id')->index();
            $table->unique(['staff_id', 'location_id']);
        });

        Schema::create('staff_service', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id')->index();
            $table->unsignedBigInteger('service_id')->index();
            $table->unique(['staff_id', 'service_id']);
        });

        if (Schema::hasColumn('staff', 'location_id')) {
            foreach (DB::table('staff')->whereNotNull('location_id')->get(['id', 'location_id']) as $row) {
                foreach (array_filter(array_map('trim', explode(',', (string) $row->location_id))) as $locId) {
                    DB::table('staff_location')->insertOrIgnore([
                        'staff_id' => $row->id,
                        'location_id' => (int) $locId,
                    ]);
                }
            }
        }

        if (Schema::hasColumn('staff', 'service_id')) {
            foreach (DB::table('staff')->whereNotNull('service_id')->get(['id', 'service_id']) as $row) {
                foreach (array_filter(array_map('trim', explode(',', (string) $row->service_id))) as $svcId) {
                    DB::table('staff_service')->insertOrIgnore([
                        'staff_id' => $row->id,
                        'service_id' => (int) $svcId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_service');
        Schema::dropIfExists('staff_location');
    }
};
