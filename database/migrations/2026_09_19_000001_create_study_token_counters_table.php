<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily study-token counters: one row per (tenant, day).
 *
 * Why a counter table instead of `SELECT MAX(token_number) … FOR UPDATE`:
 * PostgreSQL rejects `FOR UPDATE` on an aggregate query
 * (`SQLSTATE[0A000]: FOR UPDATE is not allowed with aggregate functions`),
 * so the previous allocator threw a 500 on every booking in production —
 * while SQLite, which the test suite used, silently ignores the lock and
 * kept CI green. A dedicated row gives the allocator something concrete to
 * lock, and the unique (business_id, token_date) index makes the counter
 * itself race-proof regardless of the storage engine.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('study_token_counters')) {
            return;
        }

        Schema::create('study_token_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->date('token_date');
            $table->unsignedInteger('last_token')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'token_date'], 'study_token_counters_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_token_counters');
    }
};
