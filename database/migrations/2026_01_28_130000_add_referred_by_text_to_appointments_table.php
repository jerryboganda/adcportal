<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add referred_by text column for storing referrer name directly
        if (!Schema::hasColumn('appointments', 'referred_by')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->string('referred_by', 255)->nullable()->after('notes');
            });
        }

        // Migrate existing referrer_id data to referred_by text
        // Get appointments with referrer_id and copy the name
        $appointments = \DB::table('appointments')
            ->whereNotNull('referrer_id')
            ->get();

        foreach ($appointments as $appointment) {
            $referrer = \DB::table('referrers')->find($appointment->referrer_id);
            if ($referrer) {
                \DB::table('appointments')
                    ->where('id', $appointment->id)
                    ->update(['referred_by' => $referrer->name]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'referred_by')) {
                $table->dropColumn('referred_by');
            }
        });
    }
};
