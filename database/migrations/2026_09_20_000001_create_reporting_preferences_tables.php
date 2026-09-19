<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A radiologist's own reporting setup, stored per USER rather than per browser.
 *
 * Saved worklist views and reporting preferences previously lived in
 * localStorage, so they vanished the moment the radiologist sat at a different
 * workstation — which, in a hospital reading room, is most days. They are
 * clinical WORKFLOW settings (which queue you open by default, what language
 * you dictate in), not device settings, so they belong to the user account.
 *
 * They are still tenant-scoped: a user who belongs to two clinics gets a
 * separate set of views per clinic, because a "CT chest" filter means something
 * different in each one.
 *
 * No patient data is stored here — a saved view is a set of filter values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('user_id')->index();

            // BCP-47 tag for dictation.
            $table->string('dictation_language', 12)->default('en-US');
            // browser = Web Speech recognition, server = the clinic's own
            // self-hosted transcription integration.
            $table->string('dictation_provider', 12)->default('browser');

            // Load the resolved baseline template into a new report.
            $table->boolean('template_autoload')->default(true);
            // Queue tab to open when the reporting module loads.
            $table->string('default_tab', 20)->default('unreported');

            $table->timestamps();

            // One row per radiologist per clinic.
            $table->unique(['business_id', 'user_id'], 'reporting_preferences_user_unique');
        });

        Schema::create('reporting_saved_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('name', 60);

            // The filter set only: tab, modality, priority, report status,
            // assignee, sort and the date range. Never report content.
            $table->json('filters');

            $table->timestamps();

            // A user cannot own two views with the same name — saving over one
            // updates it, which is what the "save view" button promises.
            $table->unique(['business_id', 'user_id', 'name'], 'reporting_saved_views_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_saved_views');
        Schema::dropIfExists('reporting_preferences');
    }
};
