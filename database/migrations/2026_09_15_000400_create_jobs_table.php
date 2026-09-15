<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `jobs` table for Laravel's database queue driver.
 *
 * `config/queue.php` declares `database` as the default connection, but only
 * `failed_jobs` was ever migrated — so the configured queue driver could not
 * actually accept a job. Nothing had exercised it yet (the app has no queued
 * work), which is precisely why it went unnoticed; the platform operations
 * surface (§81 "inspect job state", "retry a failed job") is the first thing
 * that needs it, and a retry is only real if it can re-queue.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
