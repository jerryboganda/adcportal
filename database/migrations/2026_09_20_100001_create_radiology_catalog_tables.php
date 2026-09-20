<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radiology_catalog_releases', function (Blueprint $table) {
            $table->id();
            $table->string('release_key', 100)->unique();
            $table->string('status', 20)->default('staging')->index();
            $table->unsignedInteger('schema_version');
            $table->string('importer_version', 30);
            $table->char('artifact_sha256', 64)->unique();
            $table->json('manifest');
            $table->json('counts');
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('radiology_catalog_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained('radiology_catalog_releases')->restrictOnDelete();
            $table->string('source_key', 80);
            $table->string('system', 100);
            $table->string('version', 80);
            $table->date('release_date');
            $table->text('url');
            $table->text('license_url');
            $table->text('attribution');
            $table->char('sha256', 64);
            $table->timestamp('retrieved_at');
            $table->json('metadata')->nullable();
            $table->unique(['release_id', 'source_key'], 'catalog_source_release_key_unique');
        });

        Schema::create('canonical_modalities', function (Blueprint $table) {
            $table->id();
            $table->string('canonical_key', 100)->unique();
            $table->string('name');
            $table->string('dicom_code', 16)->nullable();
            $table->string('kind', 30);
            $table->json('acquisition_codes');
            $table->json('source_identity');
            $table->foreignId('introduced_in_release_id')->constrained('radiology_catalog_releases')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('anatomical_regions', function (Blueprint $table) {
            $table->id();
            $table->string('canonical_key', 100)->unique();
            $table->string('name');
            $table->string('kind', 30);
            $table->json('source_identity');
            $table->foreignId('introduced_in_release_id')->constrained('radiology_catalog_releases')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('anatomical_region_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained('radiology_catalog_releases')->restrictOnDelete();
            $table->foreignId('parent_id')->constrained('anatomical_regions')->restrictOnDelete();
            $table->foreignId('child_id')->constrained('anatomical_regions')->restrictOnDelete();
            $table->string('relationship', 30);
            $table->unique(['release_id', 'parent_id', 'child_id', 'relationship'], 'catalog_region_edge_unique');
            $table->index(['release_id', 'child_id'], 'catalog_region_child_index');
        });

        Schema::create('canonical_procedures', function (Blueprint $table) {
            $table->id();
            $table->string('canonical_key', 100)->unique();
            $table->string('source_system', 100);
            $table->string('source_code', 100);
            $table->foreignId('introduced_in_release_id')->constrained('radiology_catalog_releases')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['source_system', 'source_code'], 'catalog_procedure_source_unique');
        });

        Schema::create('canonical_procedure_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canonical_procedure_id')->constrained('canonical_procedures')->restrictOnDelete();
            $table->foreignId('release_id')->constrained('radiology_catalog_releases')->restrictOnDelete();
            $table->foreignId('canonical_modality_id')->constrained('canonical_modalities')->restrictOnDelete();
            $table->foreignId('source_id')->constrained('radiology_catalog_sources')->restrictOnDelete();
            $table->string('name', 500);
            $table->string('short_name')->nullable();
            $table->text('search_name');
            $table->string('status', 30);
            $table->boolean('is_orderable');
            $table->boolean('workflow_supported');
            $table->string('clinical_category', 60);
            $table->string('contrast_category', 30);
            $table->json('contrast_routes');
            $table->json('contrast_agents');
            $table->string('laterality_policy', 30);
            $table->string('fixed_laterality', 20)->nullable();
            $table->json('views');
            $table->json('attributes');
            $table->string('pricing_category', 40)->nullable();
            $table->text('classification_basis')->nullable();
            $table->json('source_fields');
            $table->char('content_sha256', 64);
            $table->timestamps();
            $table->unique(['canonical_procedure_id', 'release_id'], 'catalog_procedure_revision_unique');
            $table->unique(['canonical_procedure_id', 'id'], 'catalog_revision_identity_unique');
            $table->index(['release_id', 'canonical_modality_id', 'status'], 'catalog_revision_search_index');
        });

        Schema::create('canonical_procedure_regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revision_id')->constrained('canonical_procedure_revisions')->restrictOnDelete();
            $table->foreignId('anatomical_region_id')->constrained('anatomical_regions')->restrictOnDelete();
            $table->string('role', 30);
            $table->unique(['revision_id', 'anatomical_region_id', 'role'], 'catalog_procedure_region_unique');
            $table->index(['anatomical_region_id', 'revision_id'], 'catalog_region_procedure_index');
        });

        Schema::create('canonical_procedure_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revision_id')->constrained('canonical_procedure_revisions')->restrictOnDelete();
            $table->string('label', 500);
            $table->string('search_label', 500);
            $table->string('language', 20)->default('en');
            $table->string('kind', 30);
            $table->text('provenance');
            $table->unique(['revision_id', 'search_label', 'language'], 'catalog_procedure_alias_unique');
        });

        Schema::create('catalog_external_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revision_id')->constrained('canonical_procedure_revisions')->restrictOnDelete();
            $table->string('system', 100);
            $table->string('code', 100);
            $table->string('version', 80);
            $table->string('display', 500);
            $table->string('relationship', 20);
            $table->text('basis');
            $table->unique(['revision_id', 'system', 'code'], 'catalog_external_mapping_unique');
            $table->index(['system', 'code'], 'catalog_external_code_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_external_mappings');
        Schema::dropIfExists('canonical_procedure_aliases');
        Schema::dropIfExists('canonical_procedure_regions');
        Schema::dropIfExists('canonical_procedure_revisions');
        Schema::dropIfExists('canonical_procedures');
        Schema::dropIfExists('anatomical_region_relations');
        Schema::dropIfExists('anatomical_regions');
        Schema::dropIfExists('canonical_modalities');
        Schema::dropIfExists('radiology_catalog_sources');
        Schema::dropIfExists('radiology_catalog_releases');
    }
};