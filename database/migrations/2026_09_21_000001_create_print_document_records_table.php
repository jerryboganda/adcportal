<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stored print documents.
 *
 * One row per (tenant, artifact, document key, paper). `finalized` marks the
 * documents that are frozen once rendered — a signed report must reprint
 * identically even after the tenant rebrands, so its file is never regenerated
 * from today's settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('print_document_records')) {
            return;
        }

        Schema::create('print_document_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->string('artifact', 40);
            $table->string('document_key', 120);
            $table->string('paper', 20);
            $table->string('driver', 20);
            $table->string('path');
            $table->unsignedBigInteger('bytes')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->boolean('finalized')->default(false);
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            // Reprints must find the original file, not create a second one.
            $table->unique(['business_id', 'artifact', 'document_key', 'paper'], 'print_document_records_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_document_records');
    }
};
