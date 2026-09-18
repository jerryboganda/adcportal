<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imaging-suite (room) reference on the booked study. Previously the
 * booking path only copied the room NAME into appointments.room_number
 * (with a fake 'Room 1' fallback); a real reference keeps the suite
 * selectable at booking, auditable, and deletable-guard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('room_id')->nullable()->index()->after('location_id');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['room_id']);
            $table->dropColumn('room_id');
        });
    }
};
