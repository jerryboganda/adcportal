<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SaaS subscription layer. A tenant is one clinic ("business" — the schema
 * carried business_id scoping from day one). Plans are platform-level records;
 * each tenant carries its own subscription state that the platform admin can
 * manage (activation is manual/offline in v1 — no gateway credentials exist yet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 12, 2)->default(0);
            $table->string('currency', 8)->default('PKR');
            $table->unsignedInteger('trial_days')->default(14);
            $table->unsignedInteger('max_users')->nullable();     // null = unlimited
            $table->unsignedInteger('max_studies_per_month')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable()->after('slug')->index();
            $table->string('subscription_status', 15)->default('trialing')->after('plan_id'); // trialing|active|suspended|expired
            $table->timestamp('trial_ends_at')->nullable()->after('subscription_status');
            $table->timestamp('subscription_ends_at')->nullable()->after('trial_ends_at');
            $table->string('tenant_code', 12)->nullable()->unique()->after('subscription_ends_at'); // public short code e.g. PX-4821
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['plan_id', 'subscription_status', 'trial_ends_at', 'subscription_ends_at', 'tenant_code']);
        });

        Schema::dropIfExists('plans');
    }
};
