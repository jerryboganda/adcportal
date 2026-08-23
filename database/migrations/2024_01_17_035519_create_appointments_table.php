<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('appointments')) {
            Schema::create('appointments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('location_id');
                $table->unsignedBigInteger('service_id');
                $table->unsignedBigInteger('staff_id');
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->string('contact')->nullable();
                $table->string('date');
                $table->string('time');
                $table->string('notes')->nullable();
                $table->string('payment_type')->nullable();
                $table->string('appointment_status')->nullable();
                $table->unsignedBigInteger('business_id')->default(0);
                $table->unsignedBigInteger('created_by')->default(0);
                $table->timestamps();

                $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
                $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
                $table->foreign('staff_id')->references('id')->on('staff')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
