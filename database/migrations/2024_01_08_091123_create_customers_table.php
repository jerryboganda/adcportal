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
        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('user_id');
                $table->string('gender');
                $table->string('dob')->nullable();
                $table->string('description')->nullable();
                $table->unsignedBigInteger('business_id')->default(0);
                $table->unsignedBigInteger('created_by')->default(0);
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

                // $table->unsignedBigInteger('billable_id');
                // $table->string('billable_type');
                // $table->timestamp('trial_ends_at')->nullable();

                // $table->index(['billable_id', 'billable_type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
