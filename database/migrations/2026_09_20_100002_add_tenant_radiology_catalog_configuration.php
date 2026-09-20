<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_catalog_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained('businesses')->restrictOnDelete();
            $table->foreignId('release_id')->nullable()->constrained('radiology_catalog_releases')->restrictOnDelete();
            $table->char('currency_code', 3)->nullable();
            $table->unsignedTinyInteger('minor_unit_precision')->default(2);
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestamp('initialized_at')->nullable();
            $table->timestamps();
        });

        Schema::table('modalities', function (Blueprint $table) {
            $table->foreignId('canonical_modality_id')->nullable()->constrained('canonical_modalities')->restrictOnDelete();
            $table->string('dicom_code', 16)->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->unique(['business_id', 'canonical_modality_id'], 'tenant_canonical_modality_unique');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('name', 500)->change();
            $table->foreignId('canonical_procedure_id')->nullable()->constrained('canonical_procedures')->restrictOnDelete();
            $table->unsignedBigInteger('canonical_procedure_revision_id')->nullable();
            $table->foreign(['canonical_procedure_id', 'canonical_procedure_revision_id'], 'service_canonical_revision_foreign')
                ->references(['canonical_procedure_id', 'id'])->on('canonical_procedure_revisions')->restrictOnDelete();
            $table->string('local_variant_key', 80)->default('');
            $table->boolean('is_active')->default(true);
            $table->foreignId('anatomical_region_id')->nullable()->constrained('anatomical_regions')->restrictOnDelete();
            $table->string('contrast_category', 30)->nullable();
            $table->string('laterality_policy', 30)->nullable();
            $table->string('fixed_laterality', 20)->nullable();
            $table->json('views')->nullable();
            $table->decimal('price', 12, 2)->nullable()->change();
            $table->char('currency_code', 3)->nullable();
            $table->unsignedTinyInteger('minor_unit_precision')->nullable();
            $table->string('pricing_state', 30)->nullable();
            $table->string('price_source', 30)->nullable();
            $table->string('price_rule_version', 60)->nullable();
            $table->timestamp('price_configured_at')->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->unique(['business_id', 'canonical_procedure_id', 'local_variant_key'], 'tenant_canonical_service_unique');
            $table->unique(['business_id', 'id'], 'tenant_service_identity_unique');
            $table->index(['business_id', 'is_active', 'modality_id'], 'tenant_catalog_service_search_index');
        });

        Schema::create('tenant_anatomical_regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->restrictOnDelete();
            $table->foreignId('anatomical_region_id')->constrained('anatomical_regions')->restrictOnDelete();
            $table->string('local_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['business_id', 'anatomical_region_id'], 'tenant_anatomical_region_unique');
        });

        Schema::create('service_aliases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('service_id');
            $table->foreign(['business_id', 'service_id'], 'service_alias_tenant_foreign')
                ->references(['business_id', 'id'])->on('services')->restrictOnDelete();
            $table->string('label');
            $table->string('search_label');
            $table->timestamps();
            $table->unique(['service_id', 'search_label'], 'service_alias_unique');
            $table->index(['business_id', 'search_label'], 'tenant_service_alias_search_index');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->json('procedure_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('procedure_snapshot');
        });
        Schema::dropIfExists('service_aliases');
        Schema::dropIfExists('tenant_anatomical_regions');
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign('service_canonical_revision_foreign');
            $table->dropForeign(['canonical_procedure_id']);
            $table->dropForeign(['anatomical_region_id']);
            $table->dropUnique('tenant_canonical_service_unique');
            $table->dropUnique('tenant_service_identity_unique');
            $table->dropIndex('tenant_catalog_service_search_index');
            $table->dropColumn([
                'canonical_procedure_id', 'canonical_procedure_revision_id', 'local_variant_key',
                'is_active', 'anatomical_region_id', 'contrast_category', 'laterality_policy',
                'fixed_laterality', 'views', 'currency_code', 'minor_unit_precision', 'pricing_state',
                'price_source', 'price_rule_version', 'price_configured_at', 'lock_version',
            ]);
        });
        Schema::table('modalities', function (Blueprint $table) {
            $table->dropForeign(['canonical_modality_id']);
            $table->dropUnique('tenant_canonical_modality_unique');
            $table->dropColumn(['canonical_modality_id', 'dicom_code', 'lock_version']);
        });
        Schema::dropIfExists('tenant_catalog_states');
    }
};