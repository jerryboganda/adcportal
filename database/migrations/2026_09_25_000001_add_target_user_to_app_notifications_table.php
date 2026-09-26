<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Address a clinical notification to one user.
 *
 * `NotificationService::push()` already accepted `target_user_id` and
 * `ReportingController::assign()` already passed it — but the column did not
 * exist and the model did not fill it, so the value was silently discarded on
 * mass-assignment. "Study Assigned for Reporting" naming a patient was
 * therefore written as a BROADCAST row and appeared in every staff member's
 * feed, in `/bootstrap`, and in the bell badge, tenant-wide.
 *
 * `NULL` keeps its meaning: an untargeted clinic-wide alert (a STAT booking, a
 * critical result, low stock) is still visible to all staff. This column only
 * distinguishes "for you" from "for the clinic".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ris_app_notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('target_user_id')->nullable();
            // The notification feed is always "my tenant, and mine-or-broadcast".
            $table->index(['business_id', 'target_user_id'], 'ris_app_notifications_business_target_index');
        });

        // No backfill: rows written before this column existed were never
        // addressed to anyone, so `NULL` is their truthful value and they stay
        // clinic-wide rather than being guessed at.
    }

    public function down(): void
    {
        Schema::table('ris_app_notifications', function (Blueprint $table) {
            $table->dropIndex('ris_app_notifications_business_target_index');
            $table->dropColumn('target_user_id');
        });
    }
};
