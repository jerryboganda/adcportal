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
        // Create referrers table for managing referring doctors
        if (!Schema::hasTable('referrers')) {
            Schema::create('referrers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('specialty')->nullable();
                $table->string('clinic')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('business_id');
                $table->unsignedBigInteger('created_by')->default(0);
                $table->timestamps();

                $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            });
        }

        // Add referrer_id to appointments table
        if (!Schema::hasColumn('appointments', 'referrer_id')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->unsignedBigInteger('referrer_id')->nullable()->after('notes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'referrer_id')) {
                $table->dropColumn('referrer_id');
            }
        });

        Schema::dropIfExists('referrers');
    }
};
