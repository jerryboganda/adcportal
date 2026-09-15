<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expand-only migration backing three verified frontend↔backend gaps:
 *
 * 1. `invoices.manual_discount` — the SPA's cash-discount input was collected
 *    but dropped before the API, so entered discounts silently never reached
 *    the invoice totals. Invoice-level discounts now persist and fold into the
 *    server-authoritative math alongside per-item discounts.
 * 2. `appointments.reading_at` — the pipeline stamps every other state
 *    transition; "sent to reading" previously borrowed `acquired_at`, which
 *    misreported when a study actually reached the reading radiologist.
 * 3. `appointments.called_at` — the queue board's "Call" button was purely
 *    client-side audio; each terminal's call was invisible to every other
 *    terminal and unaudited. Calls now persist server-side.
 * 4. `appointments.reminder_sent_at` — appointment reminders previously had no
 *    dedup marker, and the legacy command could not match the modern Y-m-d
 *    dates at all. The marker makes the reminder idempotent per study.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('manual_discount', 10, 2)->default(0)->after('discount_total');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('reading_at')->nullable()->after('acquired_at');
            $table->timestamp('called_at')->nullable()->after('in_progress_at');
            $table->timestamp('reminder_sent_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('manual_discount');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['reading_at', 'called_at', 'reminder_sent_at']);
        });
    }
};
