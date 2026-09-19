<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Radiology reporting module — professional upgrade.
 *
 *   report_templates   matching dimensions (procedure / body region / age
 *                      group / sex / contrast), tenant-vs-personal scope,
 *                      archival + version, and a structured-field schema.
 *   radiology_reports  draft optimistic-locking (`lock_version`), the values
 *                      collected by the template's structured fields, and the
 *                      template revision the text was authored against.
 *   report_macros      the tenant's macro/snippet library — clinical text
 *                      lives in DATA, never in a UI component.
 *   critical_finding_logs
 *                      critical-result communication is its own record: a
 *                      telephone notification is NOT report free text.
 *
 * Columns are added without MySQL-only `after()` positioning so the same
 * migration applies cleanly on SQLite (test suite) and PostgreSQL
 * (production).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_templates', function (Blueprint $table) {
            $table->string('code', 60)->nullable();
            $table->string('body_region', 60)->nullable();
            // any|neonatal|infant|pediatric|adolescent|adult|older_adult
            $table->string('age_group', 20)->nullable();
            // null = any sex
            $table->string('sex', 10)->nullable();
            // null = any, otherwise without|with|both
            $table->string('contrast', 20)->nullable();
            // tenant (shared) | personal (owned by the radiologist)
            $table->string('scope', 12)->default('tenant');
            $table->boolean('is_archived')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->json('structured_fields')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->index(['business_id', 'service_id', 'age_group'], 'report_templates_service_match_idx');
            $table->index(['business_id', 'modality_id', 'is_archived'], 'report_templates_modality_idx');
        });

        Schema::table('radiology_reports', function (Blueprint $table) {
            // Optimistic concurrency for drafts: two radiologists editing the
            // same report cannot silently overwrite each other.
            $table->unsignedInteger('lock_version')->default(1);
            $table->json('structured_values')->nullable();
            // Template revision the authoring session started from.
            $table->unsignedInteger('template_version')->nullable();

            $table->index(['business_id', 'locked_at'], 'radiology_reports_tenant_lock_idx');
            $table->index(['appointment_id', 'locked_at'], 'radiology_reports_study_lock_idx');
        });

        Schema::create('report_macros', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('shortcut', 40)->nullable();      // e.g. ".nctbrain"
            $table->unsignedBigInteger('modality_id')->nullable()->index();
            $table->unsignedBigInteger('service_id')->nullable()->index();
            $table->text('findings')->nullable();
            $table->text('impression')->nullable();
            $table->text('recommendations')->nullable();
            // tenant (shared library) | personal (owned by the radiologist)
            $table->string('scope', 12)->default('tenant');
            $table->boolean('is_archived')->default(false);
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['business_id', 'modality_id', 'is_archived'], 'report_macros_scope_idx');
        });

        Schema::create('critical_finding_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id')->index();
            $table->unsignedBigInteger('report_id')->nullable()->index();
            $table->text('summary');
            $table->string('notified_to', 160);
            $table->string('notified_role', 80)->nullable();
            $table->string('contact', 80)->nullable();
            // phone|in_person|sms|email|portal
            $table->string('method', 20)->default('phone');
            $table->boolean('read_back_verified')->default(false);
            $table->text('advice_given')->nullable();
            $table->timestamp('communicated_at');
            $table->unsignedBigInteger('communicated_by')->nullable();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'appointment_id'], 'critical_finding_logs_study_idx');
        });

        // Reading worklist query paths. `appointments_state_date_idx` already
        // covers unassigned state lists; these cover per-radiologist queues
        // and priority ordering inside one tenant.
        Schema::table('appointments', function (Blueprint $table) {
            // scheduled = came through booking; manual = a study record created
            // by the reporting module for an external/offline examination.
            // Stored explicitly so the acquisition board can leave these out of
            // the scanning pipeline: they enter at "acquired" because the image
            // was taken somewhere else, so there is nothing here to scan. They
            // stay visible everywhere a real study record belongs (the clinic's
            // study list and the patient's history).
            $table->string('origin', 12)->default('scheduled');

            $table->index(['business_id', 'workflow_state', 'priority'], 'appointments_tenant_state_priority_idx');
            $table->index(['business_id', 'assigned_radiologist_id', 'workflow_state'], 'appointments_tenant_assignee_idx');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_tenant_state_priority_idx');
            $table->dropIndex('appointments_tenant_assignee_idx');
            $table->dropColumn('origin');
        });

        Schema::dropIfExists('critical_finding_logs');
        Schema::dropIfExists('report_macros');

        Schema::table('radiology_reports', function (Blueprint $table) {
            $table->dropIndex('radiology_reports_tenant_lock_idx');
            $table->dropIndex('radiology_reports_study_lock_idx');
            $table->dropColumn(['lock_version', 'structured_values', 'template_version']);
        });

        Schema::table('report_templates', function (Blueprint $table) {
            $table->dropIndex('report_templates_service_match_idx');
            $table->dropIndex('report_templates_modality_idx');
            $table->dropColumn([
                'code', 'body_region', 'age_group', 'sex', 'contrast',
                'scope', 'is_archived', 'version', 'structured_fields', 'updated_by',
            ]);
        });
    }
};
