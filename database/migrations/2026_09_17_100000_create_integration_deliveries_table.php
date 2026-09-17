<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tenant-scoped delivery log for integration sends. One row per real
     * attempt through a channel — never contains secret material.
     */
    public function up(): void
    {
        // Truthful failure reporting on dispatch logs (column added alongside
        // the delivery engine; the legacy table ships without it).
        Schema::table('ris_doctor_dispatches', function (Blueprint $table) {
            if (!Schema::hasColumn('ris_doctor_dispatches', 'failure_detail')) {
                $table->string('failure_detail', 500)->nullable()->after('status');
            }
        });

        Schema::create('integration_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->foreignId('tenant_integration_id')->constrained('tenant_integrations')->cascadeOnDelete();
            $table->string('channel', 20);       // webhook | whatsapp | sms | email | hl7 | fhir
            $table->string('event', 60);         // report_dispatch | study.booked | report.released | test
            $table->string('status', 20);        // sent | failed | skipped
            $table->string('target', 255)->nullable(); // recipient / URL (not secret)
            $table->string('detail', 500)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('meta')->nullable();    // provider ids, ack codes, http status
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'created_at']);
            $table->index(['tenant_integration_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_deliveries');

        Schema::table('ris_doctor_dispatches', function (Blueprint $table) {
            if (Schema::hasColumn('ris_doctor_dispatches', 'failure_detail')) {
                $table->dropColumn('failure_detail');
            }
        });
    }
};
