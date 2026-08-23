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
        if (!Schema::hasTable('business_holidays')) {
            Schema::create('business_holidays', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->date('date');
                $table->unsignedBigInteger('business_id')->default(0);
                $table->unsignedBigInteger('created_by')->default(0);
                $table->timestamps();

                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_holidays');
    }
};
