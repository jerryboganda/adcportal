<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Questionnaire sets, e.g. "MRI Safety Screening", "Contrast Screening".
        Schema::create('screening_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('modality_id')->nullable()->index(); // auto-attach by modality
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('screening_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('screening_form_id')->index();
            $table->text('question_text');
            $table->string('help_text')->nullable();
            $table->string('answer_type', 15)->default('boolean'); // boolean|select|text
            $table->json('options')->nullable();                   // for select type
            $table->string('risk_value', 50)->nullable();          // answer value that flags risk
            $table->boolean('is_risk_blocking')->default(true);    // block acquisition until resolved
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('study_screening_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id')->index();
            $table->unsignedBigInteger('screening_question_id')->index();
            $table->string('answer_value', 255)->nullable();
            $table->boolean('is_risk')->default(false);
            $table->text('override_reason')->nullable();           // technician override for risk answers
            $table->unsignedBigInteger('answered_by')->nullable();
            $table->timestamps();

            $table->unique(['appointment_id', 'screening_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_screening_answers');
        Schema::dropIfExists('screening_questions');
        Schema::dropIfExists('screening_forms');
    }
};
