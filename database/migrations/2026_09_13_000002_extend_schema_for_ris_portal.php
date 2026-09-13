<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Column extensions that the React portal contract requires but the legacy
 * Blade schema never carried:
 *
 *  - users:      staff profile display fields + UI capability flags + last login
 *  - customers:  patient email/phone/age (previously only on guest bookings)
 *  - services:   short procedure code (e.g. DX-CHEST-PA)
 *  - appointments: assigned scan room label + radiologist rejection reason
 *  - dose_logs:  full acquisition QC/technical parameters
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('department')->nullable()->after('lang');
            $table->string('initials', 5)->nullable()->after('department');
            $table->json('capabilities')->nullable()->after('initials');
            $table->timestamp('last_login_at')->nullable()->after('capabilities');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('email')->nullable()->after('name');
            $table->string('phone', 40)->nullable()->after('email');
            $table->unsignedSmallInteger('age')->nullable()->after('dob');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('code', 40)->nullable()->after('name')->index();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('room_number', 80)->nullable()->after('priority');
            $table->text('reject_reason')->nullable()->after('cancel_reason');
        });

        // The React RIS books studies without the legacy wizard's mandatory
        // location/staff selection (staffing is decided later in the pipeline).
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable()->change();
            $table->unsignedBigInteger('staff_id')->nullable()->change();
        });

        Schema::table('dose_logs', function (Blueprint $table) {
            $table->decimal('dlp_value', 12, 3)->nullable()->after('dose_unit');
            $table->decimal('kvp', 8, 2)->nullable()->after('dlp_value');
            $table->decimal('mas', 10, 2)->nullable()->after('kvp');
            $table->unsignedInteger('slice_count')->nullable()->after('mas');
            $table->unsignedInteger('series_count')->nullable()->after('slice_count');
            $table->string('contrast_flow_rate', 30)->nullable()->after('contrast_volume_ml');
            $table->string('cannula_site', 60)->nullable()->after('contrast_flow_rate');
            $table->decimal('saline_flush_ml', 8, 2)->nullable()->after('cannula_site');
            $table->boolean('qc_passed')->default(true)->after('technique_notes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['department', 'initials', 'capabilities', 'last_login_at']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['email', 'phone', 'age']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('code');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable(false)->change();
            $table->unsignedBigInteger('staff_id')->nullable(false)->change();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['room_number', 'reject_reason']);
        });

        Schema::table('dose_logs', function (Blueprint $table) {
            $table->dropColumn([
                'dlp_value', 'kvp', 'mas', 'slice_count', 'series_count',
                'contrast_flow_rate', 'cannula_site', 'saline_flush_ml', 'qc_passed',
            ]);
        });
    }
};
