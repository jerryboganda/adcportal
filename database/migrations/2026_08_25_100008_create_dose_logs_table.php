<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dose_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id')->index();
            $table->decimal('dose_value', 10, 3)->nullable();      // DAP / CTDIvol as recorded
            $table->string('dose_unit', 15)->nullable();           // mGy.cm | mGy | mSv
            $table->string('contrast_agent')->nullable();
            $table->decimal('contrast_volume_ml', 8, 2)->nullable();
            $table->text('technique_notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dose_logs');
    }
};
