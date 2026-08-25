<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Structured report skeletons per procedure/modality.
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('service_id')->nullable()->index(); // null = global default
            $table->unsignedBigInteger('modality_id')->nullable()->index();
            $table->text('clinical_history')->nullable();
            $table->text('technique')->nullable();
            $table->text('findings')->nullable();       // section skeleton / macro text
            $table->text('impression')->nullable();
            $table->text('recommendations')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Immutable report versions; addenda chain via parent_report_id.
        Schema::create('radiology_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id')->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('type', 15)->default('draft');   // draft|preliminary|final|addendum
            $table->unsignedBigInteger('parent_report_id')->nullable();

            $table->text('clinical_history')->nullable();
            $table->text('technique')->nullable();
            $table->text('comparison')->nullable();
            $table->longText('findings')->nullable();
            $table->longText('impression')->nullable();
            $table->text('recommendations')->nullable();

            $table->boolean('critical_flag')->default(false);
            $table->timestamp('critical_acked_at')->nullable();
            $table->unsignedBigInteger('critical_acked_by')->nullable();

            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedBigInteger('authored_by')->nullable()->index();
            $table->unsignedBigInteger('signed_by')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('locked_at')->nullable();     // set once signed -> immutable

            $table->string('pdf_path')->nullable();

            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['appointment_id', 'version']);
        });

        // Audit of every delivery action for a signed report.
        Schema::create('report_releases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id')->index();
            $table->string('channel', 15);                  // print|email|portal|hand
            $table->string('recipient_email')->nullable();
            $table->unsignedBigInteger('released_by')->nullable();
            $table->timestamp('released_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_releases');
        Schema::dropIfExists('radiology_reports');
        Schema::dropIfExists('report_templates');
    }
};
