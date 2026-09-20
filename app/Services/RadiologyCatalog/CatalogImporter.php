<?php

namespace App\Services\RadiologyCatalog;

use App\Models\AuditLog;
use App\Models\CanonicalProcedureRevision;
use App\Models\RadiologyCatalogRelease;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CatalogImporter
{
    public function __construct(private readonly CatalogNormalizer $normalizer) {}

    public function preview(array $input): array
    {
        $artifact = $this->normalizer->normalize($input);
        $this->assertFixtureEnvironment($artifact);

        return $this->plan($artifact);
    }

    public function stage(array $input, string $expectedSha256): RadiologyCatalogRelease
    {
        $artifact = $this->normalizer->normalize($input);
        $this->assertFixtureEnvironment($artifact);
        $hash = hash('sha256', CatalogNormalizer::canonicalJson($artifact));
        if (! hash_equals($hash, $expectedSha256)) {
            throw ValidationException::withMessages(['sha256' => 'The normalized artifact differs from the reviewed digest.']);
        }

        return DB::transaction(function () use ($artifact, $hash) {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(?)', [726164696]);
            } elseif ($driver !== 'sqlite') {
                throw new RuntimeException('Catalog import is supported on PostgreSQL and SQLite only.');
            }
            $plan = $this->plan($artifact);
            if ($plan['conflicts'] !== []) {
                throw ValidationException::withMessages(['catalog' => $plan['conflicts']]);
            }
            $existing = RadiologyCatalogRelease::where('release_key', $artifact['release_key'])->first();
            if ($existing) {
                return $existing;
            }

            $release = RadiologyCatalogRelease::forceCreate([
                'release_key' => $artifact['release_key'],
                'status' => 'staging',
                'schema_version' => CatalogNormalizer::SCHEMA_VERSION,
                'importer_version' => CatalogNormalizer::IMPORTER_VERSION,
                'artifact_sha256' => $hash,
                'manifest' => $artifact,
                'counts' => $this->normalizer->counts($artifact),
                'validated_at' => now(),
            ]);
            $sources = [];
            foreach ($artifact['sources'] as $source) {
                $sourceId = DB::table('radiology_catalog_sources')->insertGetId([
                    'release_id' => $release->id,
                    'source_key' => $source['key'],
                    'system' => $source['system'],
                    'version' => $source['version'],
                    'release_date' => $source['release_date'],
                    'url' => $source['url'],
                    'license_url' => $source['license_url'],
                    'attribution' => $source['attribution'],
                    'sha256' => $source['sha256'],
                    'retrieved_at' => \Illuminate\Support\Carbon::parse($source['retrieved_at'])->utc(),
                    'metadata' => CatalogNormalizer::canonicalJson(['license_sha256' => $source['license_sha256']]),
                ]);
                $sources[$source['key']] = [...$source, 'id' => $sourceId];
            }

            $modalities = [];
            foreach ($artifact['modalities'] as $modality) {
                $modalities[$modality['canonical_key']] = $this->insertIdentity('canonical_modalities', $modality, $sources, $release->id, [
                    'kind' => $modality['kind'],
                    'dicom_code' => $modality['dicom_code'],
                    'acquisition_codes' => CatalogNormalizer::canonicalJson($modality['acquisition_codes']),
                ]);
            }
            $regions = [];
            foreach ($artifact['regions'] as $region) {
                $regions[$region['canonical_key']] = $this->insertIdentity('anatomical_regions', $region, $sources, $release->id, ['kind' => $region['kind']]);
            }
            $this->insertRows('anatomical_region_relations', array_map(fn ($relation) => [
                'release_id' => $release->id,
                'parent_id' => $regions[$relation['parent']],
                'child_id' => $regions[$relation['child']],
                'relationship' => $relation['relationship'],
            ], $artifact['region_relations']));

            foreach ($artifact['procedures'] as $procedure) {
                $procedureId = DB::table('canonical_procedures')->where('canonical_key', $procedure['canonical_key'])->value('id');
                if ($procedureId === null) {
                    $procedureId = DB::table('canonical_procedures')->insertGetId([
                        'canonical_key' => $procedure['canonical_key'],
                        'source_system' => $sources[$procedure['source']]['system'],
                        'source_code' => $procedure['source_code'],
                        'introduced_in_release_id' => $release->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $revision = CanonicalProcedureRevision::forceCreate([
                    'canonical_procedure_id' => $procedureId,
                    'release_id' => $release->id,
                    'canonical_modality_id' => $modalities[$procedure['modality']],
                    'source_id' => $sources[$procedure['source']]['id'],
                    'name' => $procedure['name'],
                    'short_name' => $procedure['short_name'],
                    'search_name' => $procedure['search_name'],
                    'status' => $procedure['status'],
                    'is_orderable' => $procedure['is_orderable'],
                    'workflow_supported' => $procedure['workflow_supported'],
                    'clinical_category' => $procedure['clinical_category'],
                    'contrast_category' => $procedure['contrast_category'],
                    'contrast_routes' => $procedure['contrast_routes'],
                    'contrast_agents' => $procedure['contrast_agents'],
                    'laterality_policy' => $procedure['laterality_policy'],
                    'fixed_laterality' => $procedure['fixed_laterality'],
                    'views' => $procedure['views'],
                    'attributes' => $procedure['attributes'],
                    'pricing_category' => $procedure['pricing_category'],
                    'classification_basis' => $procedure['classification_basis'],
                    'source_fields' => $procedure['source_fields'],
                    'content_sha256' => hash('sha256', CatalogNormalizer::canonicalJson($procedure)),
                ]);
                $this->insertRows('canonical_procedure_regions', array_map(fn ($region) => [
                    'revision_id' => $revision->id,
                    'anatomical_region_id' => $regions[$region['key']],
                    'role' => $region['role'],
                ], $procedure['regions']));
                $this->insertRows('canonical_procedure_aliases', array_map(fn ($alias) => [
                    'revision_id' => $revision->id,
                    'label' => $alias['label'],
                    'search_label' => $alias['search_label'],
                    'language' => $alias['language'],
                    'kind' => $alias['kind'],
                    'provenance' => $alias['provenance'],
                ], $procedure['aliases']));
                $this->insertRows('catalog_external_mappings', array_map(fn ($mapping) => [
                    'revision_id' => $revision->id,
                    'system' => $sources[$mapping['source']]['system'],
                    'code' => $mapping['code'],
                    'version' => $sources[$mapping['source']]['version'],
                    'display' => $mapping['display'],
                    'relationship' => $mapping['relationship'],
                    'basis' => $mapping['basis'],
                ], $procedure['mappings']));
            }

            AuditLog::create([
                'user_id' => auth()->id() ?? 0,
                'business_id' => null,
                'action' => 'radiology_catalog_staged',
                'subject_type' => 'RadiologyCatalogRelease',
                'subject_id' => $release->id,
                'changes' => ['release_key' => $release->release_key, 'sha256' => $hash, 'counts' => $release->counts],
                'ip' => null,
            ]);

            return $release;
        }, 3);
    }

    private function plan(array $artifact): array
    {
        $hash = hash('sha256', CatalogNormalizer::canonicalJson($artifact));
        $sources = array_column($artifact['sources'], null, 'key');
        $conflicts = [];
        $existingRelease = RadiologyCatalogRelease::where('release_key', $artifact['release_key'])->first();
        if ($existingRelease && ! hash_equals($existingRelease->artifact_sha256, $hash)) {
            $conflicts[] = 'Release identity already exists with different content.';
        }
        $new = [];
        foreach (['modalities' => 'canonical_modalities', 'regions' => 'anatomical_regions', 'procedures' => 'canonical_procedures'] as $collection => $table) {
            $new[$collection] = 0;
            $existingByKey = DB::table($table)->get()->keyBy('canonical_key');
            $existingBySource = [];
            foreach ($existingByKey as $entry) {
                $identity = $collection === 'procedures'
                    ? ['system' => $entry->source_system, 'code' => $entry->source_code]
                    : json_decode($entry->source_identity, true, 512, JSON_THROW_ON_ERROR);
                $existingBySource[$identity['system'].':'.$identity['code']] = $entry->canonical_key;
            }
            foreach ($artifact[$collection] as $entry) {
                $identity = $sources[$entry['source']]['system'].':'.$entry['source_code'];
                $existing = $existingByKey->get($entry['canonical_key']);
                if (! $existing) {
                    $new[$collection]++;
                }
                if (isset($existingBySource[$identity]) && $existingBySource[$identity] !== $entry['canonical_key']) {
                    $conflicts[] = $entry['canonical_key'].': the source identity already belongs to another canonical key.';
                }
                if ($existing && ($existingBySource[$identity] ?? null) !== $entry['canonical_key']) {
                    $conflicts[] = $entry['canonical_key'].': an existing canonical identity cannot be reassigned.';
                }
                if ($existing && $collection === 'modalities') {
                    $codes = json_decode($existing->acquisition_codes, true, 512, JSON_THROW_ON_ERROR);
                    if ($existing->kind !== $entry['kind'] || $existing->dicom_code !== $entry['dicom_code'] || $codes !== $entry['acquisition_codes']) {
                        $conflicts[] = $entry['canonical_key'].': acquisition semantics changed; use a distinct canonical modality.';
                    }
                }
                if ($existing && $collection === 'regions' && $existing->kind !== $entry['kind']) {
                    $conflicts[] = $entry['canonical_key'].': anatomical identity kind cannot change.';
                }
            }
        }
        $latestIds = DB::table('canonical_procedure_revisions')->selectRaw('MAX(id) AS id')->groupBy('canonical_procedure_id');
        $latestHashes = DB::table('canonical_procedure_revisions as revisions')
            ->joinSub($latestIds, 'latest', fn ($join) => $join->on('revisions.id', '=', 'latest.id'))
            ->join('canonical_procedures as procedures', 'procedures.id', '=', 'revisions.canonical_procedure_id')
            ->pluck('revisions.content_sha256', 'procedures.canonical_key');
        $changed = 0;
        $unchanged = 0;
        foreach ($artifact['procedures'] as $procedure) {
            $previous = $latestHashes->get($procedure['canonical_key']);
            if ($previous !== null) {
                if (hash_equals($previous, hash('sha256', CatalogNormalizer::canonicalJson($procedure)))) {
                    $unchanged++;
                } else {
                    $changed++;
                }
            }
        }

        return [
            'releaseKey' => $artifact['release_key'],
            'artifactSha256' => $hash,
            'fixture' => $artifact['fixture'],
            'counts' => $this->normalizer->counts($artifact),
            'new' => $new,
            'changedProcedures' => $changed,
            'unchangedProcedures' => $unchanged,
            'conflicts' => array_values(array_unique($conflicts)),
            'alreadyStaged' => $existingRelease !== null,
            'activatesTenants' => false,
        ];
    }

    private function insertIdentity(string $table, array $entry, array $sources, int $releaseId, array $attributes): int
    {
        $existingId = DB::table($table)->where('canonical_key', $entry['canonical_key'])->value('id');
        if ($existingId !== null) {
            return (int) $existingId;
        }

        return DB::table($table)->insertGetId([
            'canonical_key' => $entry['canonical_key'],
            'name' => $entry['name'],
            'source_identity' => CatalogNormalizer::canonicalJson([
                'system' => $sources[$entry['source']]['system'],
                'code' => $entry['source_code'],
                'version' => $sources[$entry['source']]['version'],
                'display' => $entry['name'],
            ]),
            'introduced_in_release_id' => $releaseId,
            ...$attributes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertRows(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function assertFixtureEnvironment(array $artifact): void
    {
        if ($artifact['fixture'] && ! app()->environment('testing')) {
            throw ValidationException::withMessages(['fixture' => 'Synthetic catalog fixtures cannot be imported outside tests.']);
        }
    }
}