<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Billable procedures attached to a study/visit. `appointments.service_id`
        // remains the PRIMARY procedure driving scheduling; this pivot carries
        // the primary (for invoice generation) plus any additional procedures.
        Schema::create('appointment_procedures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id')->index();
            $table->unsignedBigInteger('service_id')->nullable()->index();
            $table->string('description')->nullable();   // free-text line (contrast, film, CD)
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_procedures');
    }
};
