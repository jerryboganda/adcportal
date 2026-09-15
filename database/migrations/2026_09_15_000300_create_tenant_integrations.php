<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant integration registry (master-prompt §33/§44).
 *
 * Every integration belongs to exactly one tenant (and optionally one facility),
 * so a connection configured for Hospital A can never carry Hospital B's data.
 * Non-secret configuration lives in `config`; credentials live in `secrets`,
 * which is cast `encrypted:array` by the model — i.e. encrypted at rest with the
 * application key and never returned by the API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('type', 32);
            $table->string('name');
            $table->json('config')->nullable();
            $table->text('secrets')->nullable();
            $table->string('status', 24)->default('unconfigured');
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'name']);
            $table->index(['business_id', 'type']);
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_integrations');
    }
};
