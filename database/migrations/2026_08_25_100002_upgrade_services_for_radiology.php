<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedBigInteger('modality_id')->nullable()->after('category_id')->index();
            $table->string('body_region')->nullable()->after('name');
            $table->text('preparation_instructions')->nullable();
            $table->string('contrast_type', 20)->default('none');   // none|oral|intravenous|both
            $table->boolean('requires_screening')->default(false);
            $table->unsignedInteger('duration_minutes')->nullable(); // normalized slot length
            $table->decimal('price', 12, 2)->nullable()->change();
            $table->unsignedInteger('tat_target_hours')->default(24); // reporting turnaround target
            $table->boolean('is_bookable_online')->default(true);
        });

        // soft deletes already added by the 2026_08_25_000001 performance migration.

        // Backfill numeric durations from the legacy free-text column.
        if (Schema::hasColumn('services', 'duration')) {
            DB::statement("
                UPDATE services
                SET duration_minutes = CAST(duration AS INTEGER)
                WHERE duration GLOB '*[0-9]*'
                  AND duration NOT GLOB '*[^0-9]*'
            ");
        }
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'modality_id', 'body_region', 'preparation_instructions', 'contrast_type',
                'requires_screening', 'duration_minutes', 'tat_target_hours', 'is_bookable_online',
            ]);
        });
    }
};
