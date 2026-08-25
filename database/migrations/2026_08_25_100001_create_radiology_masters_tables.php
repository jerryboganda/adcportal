<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Imaging modalities (X-ray, US, CT, MR, MG ...)
        Schema::create('modalities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 10)->index();          // DICOM-style: CR/DX/US/CT/MR/MG
            $table->string('description')->nullable();
            $table->string('color', 9)->default('#0080b6');
            $table->unsignedInteger('buffer_minutes')->default(0); // turnover between studies
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Physical scan rooms; slots are booked against rooms per modality.
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('modality_id')->index();
            $table->unsignedBigInteger('location_id')->nullable()->index();
            $table->unsignedInteger('capacity_per_slot')->default(1);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Maintenance windows block slot generation for their rooms.
        Schema::create('equipment_downtimes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_id')->index();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_downtimes');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('modalities');
    }
};
