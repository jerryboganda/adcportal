<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Screening form slugs are only meaningful inside a clinic: the global
 * unique index blocked two tenants from both provisioning the standard
 * "mri-safety-screening" questionnaire. Uniqueness is now per tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening_forms', function (Blueprint $table) {
            $table->dropUnique('screening_forms_slug_unique');
            $table->unique(['business_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('screening_forms', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'slug']);
            $table->unique('slug');
        });
    }
};
