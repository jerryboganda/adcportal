<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RIS React-portal persistence layer.
 *
 * These tables back the React SPA collections that previously lived only in
 * browser localStorage: inventory (contrast/consumables), adverse reaction
 * reports, DICOM node registry, notification templates, in-app notifications
 * and the doctor report-dispatch log.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------------- In-app notification center ----------------
        Schema::create('ris_app_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->string('category', 20)->default('general'); // stat|workflow|billing|dispatch|pacs|security|general
            $table->string('priority', 12)->default('medium');  // low|medium|high|critical
            $table->unsignedBigInteger('appointment_id')->nullable()->index();
            $table->string('token_number', 30)->nullable();
            $table->string('patient_name')->nullable();
            $table->string('target_tab', 20)->nullable();
            $table->string('action_label')->nullable();
            $table->boolean('is_read')->default(false)->index();
            $table->unsignedBigInteger('business_id')->default(0)->index();
            $table->timestamps();
        });

        // ---------------- Doctor report dispatch log ----------------
        Schema::create('ris_doctor_dispatches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id')->nullable()->index();
            $table->string('token_number', 30)->nullable();
            $table->string('patient_name')->nullable();
            $table->unsignedBigInteger('referrer_id')->nullable()->index();
            $table->string('referrer_name')->nullable();
            $table->string('study_name')->nullable();
            $table->string('channel', 15); // whatsapp|email|sms|portal
            $table->string('recipient_contact')->nullable();
            $table->string('status', 12)->default('pending'); // delivered|read|pending
            $table->string('sent_by')->nullable();
            $table->unsignedBigInteger('business_id')->default(0)->index();
            $table->timestamps();
        });

        // ---------------- DICOM node registry ----------------
        Schema::create('ris_dicom_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('node_name');
            $table->string('ae_title');
            $table->string('ip_address', 45);
            $table->unsignedSmallInteger('port');
            $table->string('modality_code', 10)->nullable();
            $table->boolean('is_worklist_scp')->default(false);
            $table->boolean('is_storage_scp')->default(false);
            $table->string('status', 15)->default('unreachable'); // online|unreachable|testing
            $table->string('last_ping_time')->nullable();
            $table->unsignedInteger('last_ping_latency_ms')->nullable();
            $table->unsignedBigInteger('business_id')->default(0)->index();
            $table->timestamps();
        });

        // ---------------- Patient notification templates ----------------
        Schema::create('ris_notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 20); // booking|checkin|ready|critical|doctor
            $table->string('channel', 15);  // sms|whatsapp|email
            $table->string('subject')->nullable();
            $table->text('template_body');
            $table->boolean('enabled')->default(true);
            $table->unsignedBigInteger('business_id')->default(0)->index();
            $table->timestamps();
        });

        // ---------------- Clinical inventory ----------------
        Schema::create('ris_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('name');
            $table->string('generic_name')->nullable();
            $table->string('category', 30); // contrast_ct|contrast_mri|cannula_syringes|ppe_safety|pharmacy_emergency|general_consumable
            $table->string('modality', 10)->default('ALL'); // CT|MRI|XRAY|US|ALL
            $table->string('unit', 50)->nullable();
            $table->unsignedInteger('current_stock')->default(0);
            $table->unsignedInteger('min_threshold')->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->json('batches')->nullable(); // [{batch_number, expiry_date, quantity, received_date}]
            $table->string('supplier')->nullable();
            $table->string('storage_location')->nullable();
            $table->boolean('requires_cold_chain')->default(false);
            $table->boolean('is_billable')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('business_id')->default(0)->index();
            $table->unique(['business_id', 'code']);
            $table->timestamps();
        });

        Schema::create('ris_inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_item_id')->index();
            $table->string('item_name')->nullable();
            $table->string('type', 20); // usage_study|stock_in|adjustment|wastage|expired_discard
            $table->integer('quantity');
            $table->string('batch_number', 60)->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable()->index();
            $table->string('token_number', 30)->nullable();
            $table->string('patient_name')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->unsignedBigInteger('business_id')->default(0)->index();
            $table->timestamps();
        });

        // ---------------- Contrast adverse reaction register ----------------
        Schema::create('ris_adverse_reactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id')->nullable()->index();
            $table->string('token_number', 30)->nullable();
            $table->string('patient_name')->nullable();
            $table->string('modality', 10)->nullable(); // CT|MRI
            $table->string('contrast_agent')->nullable();
            $table->string('batch_number', 60)->nullable();
            $table->string('administered_volume', 30)->nullable();
            $table->string('severity', 25); // mild|moderate|severe_anaphylaxis|extravasation
            $table->json('symptoms')->nullable();
            $table->text('treatment_given')->nullable();
            $table->string('outcome', 25); // resolved_on_site|referred_to_er|under_observation
            $table->string('reported_by')->nullable();
            $table->string('supervising_doctor')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('business_id')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ris_adverse_reactions');
        Schema::dropIfExists('ris_inventory_transactions');
        Schema::dropIfExists('ris_inventory_items');
        Schema::dropIfExists('ris_notification_templates');
        Schema::dropIfExists('ris_dicom_nodes');
        Schema::dropIfExists('ris_doctor_dispatches');
        Schema::dropIfExists('ris_app_notifications');
    }
};
