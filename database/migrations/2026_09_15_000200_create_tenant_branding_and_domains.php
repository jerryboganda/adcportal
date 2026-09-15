<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant white-labeling + custom domain registry (master-prompt §37/§38).
 *
 * `tenant_branding` holds presentation-only overrides (application name,
 * colours, logo, report header/footer, email identity) — one row per tenant.
 * `tenant_domains` is the registry of hosts that resolve to a tenant so the
 * login screen and the app shell can render the right brand.
 *
 * SECURITY: a matching host only ever selects *presentation*. It is never an
 * authorization input — authentication and tenant resolution stay on the
 * authenticated principal (see docs/saas/SECURITY_MODEL.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_branding', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained('businesses')->cascadeOnDelete();
            $table->string('app_name')->nullable();
            $table->string('primary_color', 32)->nullable();
            $table->string('accent_color', 32)->nullable();
            $table->string('logo_url', 1024)->nullable();
            $table->string('favicon_url', 1024)->nullable();
            $table->string('login_message', 500)->nullable();
            $table->text('report_header')->nullable();
            $table->text('report_footer')->nullable();
            $table->string('email_from_name')->nullable();
            $table->string('email_from_address')->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_phone', 50)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('host')->unique();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenant_branding');
    }
};
