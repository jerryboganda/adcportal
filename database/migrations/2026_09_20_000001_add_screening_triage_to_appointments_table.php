<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI safety-screening triage (TypeSafe System One / Jev via the Vercel AI
 * Gateway). Advisory clinical signal computed server-side at screening
 * submission; the deterministic flagsRisk() gate stays authoritative and is
 * never derived from this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->json('screening_triage')->nullable()->after('screening_cleared');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('screening_triage');
        });
    }
};
