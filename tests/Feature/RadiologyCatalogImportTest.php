<?php

namespace Tests\Feature;

use App\Models\CanonicalProcedureRevision;
use App\Models\RadiologyCatalogRelease;
use App\Services\RadiologyCatalog\CatalogImporter;
use App\Services\RadiologyCatalog\TenantCatalogService;
use App\Support\BookingMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\RadiologyCatalogFixture;

class RadiologyCatalogImportTest extends ApiTestCase
{
    public function test_preview_is_read_only_and_does_not_change_tenant_prices(): void
    {
        $service = $this->tenantService($this->businessA, 'CT-BRAIN-NC');
        $service->update(['price' => '7123.45']);
        $before = $this->databaseCounts();

        $preview = app(CatalogImporter::class)->preview(RadiologyCatalogFixture::make());

        $this->assertSame($before, $this->databaseCounts());
        $this->assertSame(9, $preview['counts']['procedures']);
        $this->assertSame(9, $preview['new']['procedures']);
        $this->assertSame([], $preview['conflicts']);
        $this->assertFalse($preview['activatesTenants']);
        $this->assertEquals('7123.45', $service->fresh()->price);
    }

    public function test_staging_is_idempotent_atomic_and_never_activates_tenants(): void
    {
        $input = RadiologyCatalogFixture::make();
        $importer = app(CatalogImporter::class);
        $hash = $importer->preview($input)['artifactSha256'];
        $servicesBefore = DB::table('services')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $first = $importer->stage($input, $hash);
        $second = $importer->stage($input, $hash);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('staging', $first->status);
        $this->assertNull($first->published_at);
        $this->assertDatabaseCount('radiology_catalog_releases', 1);
        $this->assertDatabaseCount('radiology_catalog_sources', 1);
        $this->assertDatabaseCount('canonical_modalities', 4);
        $this->assertDatabaseCount('anatomical_regions', 4);
        $this->assertDatabaseCount('anatomical_region_relations', 2);
        $this->assertDatabaseCount('canonical_procedures', 9);
        $this->assertDatabaseCount('canonical_procedure_revisions', 9);
        $this->assertDatabaseCount('canonical_procedure_aliases', 9);
        $this->assertDatabaseCount('tenant_catalog_states', 0);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'radiology_catalog_staged')->count());
        $this->assertSame($servicesBefore, DB::table('services')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertSame(9, $importer->preview($input)['unchangedProcedures']);
    }

    public function test_staging_rejects_an_unreviewed_digest_without_writes(): void
    {
        $before = $this->databaseCounts();
        try {
            app(CatalogImporter::class)->stage(RadiologyCatalogFixture::make(), str_repeat('0', 64));
            $this->fail('A mismatched digest was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sha256', $exception->errors());
        }
        $this->assertSame($before, $this->databaseCounts());
    }

    public function test_staging_rejects_reassigned_release_and_source_identities(): void
    {
        $input = RadiologyCatalogFixture::make();
        $this->stage($input);
        $before = $this->databaseCounts();
        $input['procedures'][0]['name'] = 'Synthetic new label';
        $input['procedures'][0]['source_fields']['DISPLAY'] = 'Synthetic new label';

        $importer = app(CatalogImporter::class);
        $preview = $importer->preview($input);
        $this->assertNotEmpty($preview['conflicts']);
        try {
            $importer->stage($input, $preview['artifactSha256']);
            $this->fail('An existing release was overwritten.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('catalog', $exception->errors());
        }
        $input['release_key'] = 'test:radiology-v2';
        $input['procedures'][0]['source_code'] = 'DIFFERENT-SYNTHETIC-CODE';
        $input['procedures'][0]['source_fields']['CODE'] = 'DIFFERENT-SYNTHETIC-CODE';
        $preview = $importer->preview($input);
        $this->assertNotEmpty($preview['conflicts']);
        try {
            $importer->stage($input, $preview['artifactSha256']);
            $this->fail('A canonical identity was reassigned.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('catalog', $exception->errors());
        }
        $this->assertSame($before, $this->databaseCounts());
    }

    public function test_new_release_creates_a_revision_without_rewriting_history(): void
    {
        $input = RadiologyCatalogFixture::make();
        $first = $this->stage($input);
        $original = $first->revisions()->where('name', $input['procedures'][0]['name'])->firstOrFail();
        $input['release_key'] = 'test:radiology-v2';
        $input['procedures'][0]['name'] = 'Synthetic revised label';
        $input['procedures'][0]['source_fields']['DISPLAY'] = 'Synthetic revised label';
        $preview = app(CatalogImporter::class)->preview($input);
        $second = $this->stage($input);
        $revised = $second->revisions()->where('canonical_procedure_id', $original->canonical_procedure_id)->firstOrFail();

        $this->assertSame(1, $preview['changedProcedures']);
        $this->assertSame(8, $preview['unchangedProcedures']);
        $this->assertSame('Synthetic ct-without', $original->fresh()->name);
        $this->assertSame('Synthetic revised label', $revised->name);
        $this->assertSame($original->canonical_procedure_id, $revised->canonical_procedure_id);
        $this->assertDatabaseCount('canonical_procedures', 9);
        $this->assertDatabaseCount('canonical_procedure_revisions', 18);

        $this->expectException(\LogicException::class);
        $original->forceFill(['name' => 'Forbidden overwrite'])->save();
    }

    public function test_mid_import_failure_rolls_back_the_entire_staged_release(): void
    {
        $input = RadiologyCatalogFixture::make();
        $importer = app(CatalogImporter::class);
        $hash = $importer->preview($input)['artifactSha256'];
        $before = $this->databaseCounts();
        $created = 0;
        $event = 'eloquent.creating: '.CanonicalProcedureRevision::class;
        Event::listen($event, function () use (&$created) {
            if (++$created === 2) {
                throw new \RuntimeException('Synthetic interrupted import.');
            }
        });

        try {
            $importer->stage($input, $hash);
            $this->fail('The interrupted import succeeded.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic interrupted import.', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame($before, $this->databaseCounts());
        $this->assertSame('staging', $importer->stage($input, $hash)->status);
    }

    public function test_synthetic_sources_cannot_be_imported_outside_tests(): void
    {
        $this->app->instance('env', 'production');
        try {
            app(CatalogImporter::class)->preview(RadiologyCatalogFixture::make());
            $this->fail('A synthetic production catalog was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fixture', $exception->errors());
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertDatabaseCount('radiology_catalog_releases', 0);
    }

    public function test_schema_preserves_distinction_between_null_and_zero_prices(): void
    {
        $service = $this->tenantService($this->businessA, 'CT-BRAIN-NC');
        $service->update(['price' => null, 'pricing_state' => 'review', 'price_source' => 'tenant']);
        $this->assertNull($service->fresh()->price);

        $service->update(['price' => '0.00', 'pricing_state' => 'configured']);
        $this->assertNotNull($service->fresh()->price);
        $this->assertEquals(0, $service->fresh()->price);
    }

    public function test_only_published_releases_can_initialize_tenants(): void
    {
        $release = $this->stage(RadiologyCatalogFixture::make());
        $before = $this->databaseCounts();
        try {
            app(TenantCatalogService::class)->initialize($this->businessA, $release, 'PKR', 2);
            $this->fail('A staged release initialized a tenant.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('release', $exception->errors());
        }
        $this->assertSame($before, $this->databaseCounts());
    }

    public function test_initialization_persists_owner_prices_without_guessing_operational_settings(): void
    {
        $release = $this->publishedFixture();
        $result = app(TenantCatalogService::class)->initialize($this->businessA, $release, 'PKR', 2);
        $this->assertSame(9, $result['services']);
        $this->assertSame(8, $result['pricedServices']);
        $this->assertSame(1, $result['unpricedServices']);

        foreach ([
            'ct-without' => 200000, 'ct-with' => 350000, 'ct-combined' => 350000,
            'mr-without' => 300000, 'mr-with' => 400000, 'mr-combined' => 400000,
            'us-general' => 20000, 'dx-general' => 30000, 'ct-special' => null,
        ] as $key => $expected) {
            $service = $this->catalogService($this->businessA, 'test:'.$key);
            $actual = $service->price === null ? null : BookingMoney::decimalToMinor((string) $service->price);
            $this->assertSame($expected, $actual, $key);
            $this->assertSame('PKR', $service->currency_code);
            $this->assertTrue($service->is_active);
            $this->assertFalse($service->is_bookable_online);
            $this->assertNull($service->duration_minutes);
            $this->assertSame('', $service->duration);
            $this->assertNull($service->preparation_instructions);
        }
        $this->assertEquals(6500, $this->tenantService($this->businessA, 'CT-BRAIN-NC')->price);
        $this->assertDatabaseCount('rooms', 0);
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_initialization_retry_preserves_explicit_prices_cleared_prices_and_deleted_state(): void
    {
        $release = $this->publishedFixture();
        $catalog = app(TenantCatalogService::class);
        $catalog->initialize($this->businessA, $release, 'PKR', 2);
        $service = $this->catalogService($this->businessA, 'test:ct-without');
        $service->update(['name' => 'Tenant label', 'price' => '6500.00', 'price_source' => 'tenant', 'duration_minutes' => 37]);
        $zero = $this->catalogService($this->businessA, 'test:ct-with');
        $zero->update(['price' => '0.00', 'price_source' => 'tenant']);
        $cleared = $this->catalogService($this->businessA, 'test:ct-combined');
        $cleared->update(['price' => null, 'pricing_state' => 'review', 'price_source' => 'tenant']);
        $deleted = $this->catalogService($this->businessA, 'test:mr-without');
        $modality = $deleted->modality;
        $deleted->delete();
        $modality->delete();
        DB::table('tenant_anatomical_regions')->where('business_id', $this->businessA->id)->update(['is_active' => false]);
        $before = $this->databaseCounts();
        $version = DB::table('tenant_catalog_states')->where('business_id', $this->businessA->id)->value('lock_version');

        $catalog->initialize($this->businessA, $release, 'PKR', 2);
        $catalog->initialize($this->businessA, $release, 'PKR', 2);

        $this->assertSame($before, $this->databaseCounts());
        $this->assertSame($version, DB::table('tenant_catalog_states')->where('business_id', $this->businessA->id)->value('lock_version'));
        $this->assertEquals(6500, $service->fresh()->price);
        $this->assertSame('Tenant label', $service->fresh()->name);
        $this->assertSame(37, $service->fresh()->duration_minutes);
        $this->assertEquals(0, $zero->fresh()->price);
        $this->assertNotNull($zero->fresh()->price);
        $this->assertNull($cleared->fresh()->price);
        $this->assertSame('review', $cleared->fresh()->pricing_state);
        $this->assertTrue($deleted->fresh()->trashed());
        $this->assertTrue($modality->fresh()->trashed());
        $this->assertSame(0, DB::table('tenant_anatomical_regions')->where('business_id', $this->businessA->id)->where('is_active', true)->count());
    }

    public function test_tenant_prices_are_isolated_and_catalog_activation_does_not_reactivate_a_subscription(): void
    {
        $release = $this->publishedFixture();
        $catalog = app(TenantCatalogService::class);
        $catalog->initialize($this->businessA, $release, 'PKR', 2);
        $this->businessB->update(['subscription_status' => 'suspended']);
        $catalog->initialize($this->businessB, $release, 'PKR', 2);
        $this->catalogService($this->businessA, 'test:ct-without')->update(['price' => '6500.00', 'is_active' => false]);

        $other = $this->catalogService($this->businessB, 'test:ct-without');
        $this->assertEquals(2000, $other->price);
        $this->assertTrue($other->is_active);
        $this->assertSame('suspended', $this->businessB->fresh()->subscription_status);
    }

    public function test_non_pkr_currency_is_not_relabelled_or_assigned_pkr_prices(): void
    {
        $release = $this->publishedFixture();
        $setting = \App\Models\Setting::where('business', $this->businessA->id)->where('key', 'ris_clinic_profile')->firstOrFail();
        $profile = json_decode($setting->value, true, 512, JSON_THROW_ON_ERROR);
        $setting->update(['value' => json_encode([...$profile, 'currencyCode' => 'USD'], JSON_THROW_ON_ERROR)]);
        $catalog = app(TenantCatalogService::class);
        try {
            $catalog->initialize($this->businessA, $release, 'PKR', 2);
            $this->fail('The configured clinic currency was ignored.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('currency', $exception->errors());
        }

        $result = $catalog->initialize($this->businessA, $release, 'USD', 2);
        $this->assertSame(0, $result['pricedServices']);
        $this->assertSame(9, $result['unpricedServices']);
        $service = $this->catalogService($this->businessA, 'test:ct-without');
        $this->assertNull($service->price);
        $this->assertSame('USD', $service->currency_code);
        $this->assertSame('unconfigured', $service->pricing_state);
    }

    public function test_alias_foreign_key_rejects_cross_tenant_service_references(): void
    {
        $service = $this->tenantService($this->businessB, 'CT-BRAIN-NC');
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('service_aliases')->insert([
            'business_id' => $this->businessA->id,
            'service_id' => $service->id,
            'label' => 'Invalid cross-tenant alias',
            'search_label' => 'invalid cross tenant alias',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_service_revision_foreign_key_rejects_a_different_concept(): void
    {
        $release = $this->stage(RadiologyCatalogFixture::make());
        $revisions = $release->revisions()->orderBy('id')->limit(2)->get();
        $service = $this->tenantService($this->businessA, 'CT-BRAIN-NC');

        $this->expectException(\Illuminate\Database\QueryException::class);
        $service->update([
            'canonical_procedure_id' => $revisions[0]->canonical_procedure_id,
            'canonical_procedure_revision_id' => $revisions[1]->id,
        ]);
    }

    public function test_operator_command_defaults_to_validation_and_import_requires_the_digest(): void
    {
        Storage::fake('local');
        $input = RadiologyCatalogFixture::make();
        Storage::disk('local')->put('synthetic-catalog.json', json_encode($input, JSON_THROW_ON_ERROR));
        $path = Storage::disk('local')->path('synthetic-catalog.json');
        $this->artisan('ris:catalog', ['artifact' => $path])->assertExitCode(0);
        $this->assertDatabaseCount('radiology_catalog_releases', 0);
        $this->artisan('ris:catalog', ['action' => 'dry-run', 'artifact' => $path])->assertExitCode(0);
        $this->assertDatabaseCount('radiology_catalog_releases', 0);
        $this->artisan('ris:catalog', ['action' => 'import', 'artifact' => $path])->assertExitCode(1);
        $this->assertDatabaseCount('radiology_catalog_releases', 0);

        $hash = app(CatalogImporter::class)->preview($input)['artifactSha256'];
        $this->artisan('ris:catalog', ['action' => 'import', 'artifact' => $path, '--sha256' => $hash])->assertExitCode(0);
        $this->artisan('ris:catalog', ['action' => 'report', '--release' => $input['release_key']])->assertExitCode(0);
        $this->artisan('ris:catalog', ['action' => 'publish', 'artifact' => $path])->assertExitCode(1);
        $this->artisan('ris:catalog', ['action' => 'reset', 'artifact' => $path])->assertExitCode(1);
        $this->assertDatabaseCount('radiology_catalog_releases', 1);
        $this->assertSame('staging', RadiologyCatalogRelease::firstOrFail()->status);
    }

    private function stage(array $input): RadiologyCatalogRelease
    {
        $importer = app(CatalogImporter::class);

        return $importer->stage($input, $importer->preview($input)['artifactSha256']);
    }

    private function publishedFixture(): RadiologyCatalogRelease
    {
        $release = $this->stage(RadiologyCatalogFixture::make());
        DB::table('radiology_catalog_releases')->where('id', $release->id)->update(['status' => 'published', 'published_at' => now()]);

        return $release->fresh();
    }

    private function catalogService(\App\Models\Business $business, string $key): \App\Models\Service
    {
        return \App\Models\Service::forClinic($business->id)
            ->whereHas('canonicalProcedure', fn ($query) => $query->where('canonical_key', $key))->firstOrFail();
    }

    private function databaseCounts(): array
    {
        $counts = [];
        foreach ([
            'radiology_catalog_releases', 'radiology_catalog_sources', 'canonical_modalities',
            'anatomical_regions', 'anatomical_region_relations', 'canonical_procedures',
            'canonical_procedure_revisions', 'canonical_procedure_regions',
            'canonical_procedure_aliases', 'catalog_external_mappings', 'tenant_catalog_states',
            'services', 'modalities', 'appointments', 'invoices', 'audit_logs',
        ] as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }
}