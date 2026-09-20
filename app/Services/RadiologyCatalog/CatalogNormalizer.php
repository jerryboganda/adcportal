<?php

namespace App\Services\RadiologyCatalog;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CatalogNormalizer
{
    public const SCHEMA_VERSION = 1;
    public const IMPORTER_VERSION = '1.0.0';
    public const ACQUISITION_CODES = ['CT', 'MR', 'US', 'CR', 'DX', 'MG', 'RF', 'XA', 'NM', 'PT', 'BMD'];

    public function normalize(array $input): array
    {
        $key = ['required', 'string', 'max:100', 'regex:/\A[a-z0-9][a-z0-9._:-]+\z/D'];
        $hash = ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/D'];
        $identity = [
            'canonical_key' => $key,
            'source' => ['required', 'string', 'max:80'],
            'source_code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'source_fields' => ['required', 'array', 'min:1', 'max:500'],
            'source_code_field' => ['required', 'string', 'max:100'],
            'source_name_field' => ['required', 'string', 'max:100'],
        ];
        $rules = [
            'schema_version' => ['required', 'integer', Rule::in([self::SCHEMA_VERSION])],
            'release_key' => $key,
            'fixture' => ['required', 'boolean'],
            'sources' => ['required', 'array', 'min:1', 'max:100'],
            'sources.*.key' => ['required', 'string', 'max:80', 'distinct:strict'],
            'sources.*.system' => ['required', Rule::in(['DICOM', 'LOINC', 'RADLEX', 'RSNA_PLAYBOOK', 'APPLICATION', 'TEST'])],
            'sources.*.version' => ['required', 'string', 'max:80'],
            'sources.*.release_date' => ['required', 'date_format:Y-m-d'],
            'sources.*.url' => ['required', 'url:https', 'max:2048'],
            'sources.*.license_url' => ['required', 'url:https', 'max:2048'],
            'sources.*.license_sha256' => $hash,
            'sources.*.attribution' => ['required', 'string', 'max:20000'],
            'sources.*.sha256' => $hash,
            'sources.*.retrieved_at' => ['required', 'date'],
            'modalities' => ['required', 'array', 'min:1', 'max:100'],
            'modalities.*.kind' => ['required', Rule::in(['acquisition', 'composite'])],
            'modalities.*.dicom_code' => ['present', 'nullable', Rule::in(self::ACQUISITION_CODES)],
            'modalities.*.acquisition_codes' => ['required', 'array', 'min:1', 'max:5'],
            'modalities.*.acquisition_codes.*' => ['required', Rule::in(self::ACQUISITION_CODES)],
            'regions' => ['present', 'array', 'max:50000'],
            'regions.*.kind' => ['required', Rule::in(['structure', 'composite', 'group'])],
            'region_relations' => ['present', 'array', 'max:100000'],
            'region_relations.*.parent' => $key,
            'region_relations.*.child' => $key,
            'region_relations.*.relationship' => ['required', Rule::in(['navigation', 'part_of', 'composite_member'])],
            'procedures' => ['required', 'array', 'min:1', 'max:50000'],
            'procedures.*.modality' => $key,
            'procedures.*.short_name' => ['present', 'nullable', 'string', 'max:255'],
            'procedures.*.status' => ['required', Rule::in(['active', 'deprecated', 'discouraged', 'trial'])],
            'procedures.*.is_orderable' => ['required', 'boolean'],
            'procedures.*.workflow_supported' => ['required', 'boolean'],
            'procedures.*.clinical_category' => ['required', 'string', 'max:60'],
            'procedures.*.contrast_category' => ['required', Rule::in(['without', 'with', 'with_and_without', 'not_applicable', 'unknown'])],
            'procedures.*.contrast_routes' => ['present', 'array', 'max:10'],
            'procedures.*.contrast_routes.*' => ['required', Rule::in(['oral', 'intravenous', 'intra_articular', 'intrathecal', 'rectal', 'intracavitary', 'other', 'unknown'])],
            'procedures.*.contrast_agents' => ['present', 'array', 'max:20'],
            'procedures.*.contrast_agents.*' => ['required', 'string', 'max:255'],
            'procedures.*.laterality_policy' => ['required', Rule::in(['not_applicable', 'optional', 'required', 'fixed', 'unknown'])],
            'procedures.*.fixed_laterality' => ['present', 'nullable', Rule::in(['left', 'right', 'bilateral'])],
            'procedures.*.views' => ['present', 'array', 'max:50'],
            'procedures.*.views.*' => ['required', 'string', 'max:255'],
            'procedures.*.attributes' => ['present', 'array', 'max:100'],
            'procedures.*.pricing_category' => ['present', 'nullable', Rule::in(['ct_standard', 'mr_standard', 'us_standard', 'radiography_standard'])],
            'procedures.*.classification_basis' => ['present', 'nullable', 'string', 'max:4000'],
            'procedures.*.regions' => ['present', 'array', 'max:100'],
            'procedures.*.regions.*.key' => $key,
            'procedures.*.regions.*.role' => ['required', Rule::in(['primary', 'region_imaged', 'focus', 'additional'])],
            'procedures.*.aliases' => ['present', 'array', 'max:200'],
            'procedures.*.aliases.*.label' => ['required', 'string', 'max:500'],
            'procedures.*.aliases.*.language' => ['required', 'string', 'max:20'],
            'procedures.*.aliases.*.kind' => ['required', Rule::in(['source', 'application'])],
            'procedures.*.aliases.*.provenance' => ['required', 'string', 'max:4000'],
            'procedures.*.mappings' => ['present', 'array', 'max:100'],
            'procedures.*.mappings.*.source' => ['required', 'string', 'max:80'],
            'procedures.*.mappings.*.code' => ['required', 'string', 'max:100'],
            'procedures.*.mappings.*.display' => ['required', 'string', 'max:500'],
            'procedures.*.mappings.*.relationship' => ['required', Rule::in(['exact', 'related'])],
            'procedures.*.mappings.*.basis' => ['required', 'string', 'max:4000'],
            'exclusions' => ['present', 'array', 'max:100000'],
            'quarantines' => ['present', 'array', 'max:100000'],
            'discovered_procedures' => ['required', 'integer', 'min:1'],
            'expected_counts' => ['required', 'array'],
        ];
        foreach (['modalities', 'regions', 'procedures'] as $collection) {
            foreach ($identity as $field => $rule) {
                $rules[$collection.'.*.'.$field] = $rule;
            }
        }
        $rules['procedures.*.name'] = ['required', 'string', 'max:500'];
        foreach (['exclusions', 'quarantines'] as $collection) {
            $rules[$collection.'.*.source'] = ['required', 'string', 'max:80'];
            $rules[$collection.'.*.source_code'] = ['required', 'string', 'max:100'];
            $rules[$collection.'.*.reason'] = ['required', 'string', 'max:4000'];
        }
        foreach (['sources', 'modalities', 'regions', 'region_relations', 'procedures', 'aliases', 'mappings', 'exclusions', 'quarantines'] as $field) {
            $rules['expected_counts.'.$field] = ['required', 'integer', 'min:0'];
        }
        $artifact = Validator::make($input, $rules)->validate();
        $artifact['fixture'] = (bool) $artifact['fixture'];
        $sources = array_column($artifact['sources'], null, 'key');

        foreach ($sources as $source) {
            $this->require($source['system'] !== 'TEST' || $artifact['fixture'], 'Synthetic sources require a fixture artifact.');
        }
        foreach (['modalities', 'regions', 'procedures'] as $collection) {
            $this->unique(array_column($artifact[$collection], 'canonical_key'), $collection.' canonical identities');
            $sourceIdentities = [];
            foreach ($artifact[$collection] as $entry) {
                $this->require(isset($sources[$entry['source']]), 'Unknown source reference in '.$collection.'.');
                $this->require(($entry['source_fields'][$entry['source_code_field']] ?? null) === $entry['source_code'], 'Source code differs from the preserved source field.');
                $this->require(($entry['source_fields'][$entry['source_name_field']] ?? null) === $entry['name'], 'Source name differs from the preserved source field.');
                $sourceIdentities[] = $sources[$entry['source']]['system'].':'.$entry['source_code'];
            }
            $this->unique($sourceIdentities, $collection.' source identities');
        }

        $modalities = array_column($artifact['modalities'], null, 'canonical_key');
        $regions = array_column($artifact['regions'], null, 'canonical_key');
        foreach ($modalities as $modality) {
            $this->unique($modality['acquisition_codes'], 'acquisition components');
            if ($modality['kind'] === 'acquisition') {
                $this->require($modality['acquisition_codes'] === [$modality['dicom_code']], 'An acquisition modality must use exactly its verified DICOM code.');
                $this->require($artifact['fixture'] || $sources[$modality['source']]['system'] === 'DICOM', 'Acquisition modalities require DICOM source evidence.');
            } else {
                $this->require($modality['dicom_code'] === null && count($modality['acquisition_codes']) > 1, 'A composite modality needs distinct acquisition components, not an invented DICOM code.');
            }
        }
        $this->validateRegionGraph($regions, $artifact['region_relations']);

        $exactOwners = [];
        foreach ($artifact['procedures'] as &$procedure) {
            $this->require(isset($modalities[$procedure['modality']]), 'Unknown procedure modality.');
            $sourceSystem = $sources[$procedure['source']]['system'];
            $this->require($artifact['fixture'] || in_array($sourceSystem, ['LOINC', 'RSNA_PLAYBOOK'], true), 'Production procedures require an authoritative orderable source.');
            $identityKey = self::canonicalJson([$sourceSystem, $procedure['source_code']]);
            $this->require(! isset($exactOwners[$identityKey]) || $exactOwners[$identityKey] === $procedure['canonical_key'], 'An exact source code maps to multiple procedures.');
            $exactOwners[$identityKey] = $procedure['canonical_key'];
            $procedure['is_orderable'] = (bool) $procedure['is_orderable'];
            $procedure['workflow_supported'] = (bool) $procedure['workflow_supported'];
            if ($procedure['is_orderable']) {
                $this->require($procedure['status'] === 'active' && $procedure['workflow_supported'], 'Unsupported or inactive procedures cannot be orderable.');
                $this->require($procedure['contrast_category'] !== 'unknown' && $procedure['laterality_policy'] !== 'unknown', 'Ambiguous clinical attributes cannot be orderable.');
                $this->require(! in_array('unknown', $procedure['contrast_routes'], true), 'Unknown contrast administration routes cannot be orderable.');
                $this->require($procedure['regions'] !== [], 'Orderable procedures need verified anatomy.');
            }
            $hasContrast = in_array($procedure['contrast_category'], ['with', 'with_and_without'], true);
            if (in_array($procedure['contrast_category'], ['without', 'not_applicable'], true)) {
                $this->require($procedure['contrast_routes'] === [] && $procedure['contrast_agents'] === [], 'Non-contrast procedures cannot carry contrast administration attributes.');
            }
            $this->require(! $hasContrast || $procedure['contrast_routes'] !== [], 'Contrast procedures must preserve their source route, including explicit unknown.');
            $this->require(($procedure['laterality_policy'] === 'fixed') === ($procedure['fixed_laterality'] !== null), 'Fixed laterality and laterality policy disagree.');
            $regionKeys = [];
            $primaryCount = 0;
            foreach ($procedure['regions'] as $region) {
                $this->require(isset($regions[$region['key']]), 'Unknown procedure region.');
                $regionKeys[] = $region['key'].':'.$region['role'];
                $primaryCount += (int) ($region['role'] === 'primary');
            }
            $this->unique($regionKeys, 'procedure region roles');
            $this->require($primaryCount <= 1, 'A procedure has more than one primary navigation region.');
            $this->validatePricingClassification($procedure, $modalities[$procedure['modality']]);

            $aliasKeys = [];
            foreach ($procedure['aliases'] as &$alias) {
                $alias['search_label'] = self::searchText($alias['label']);
                $this->require($alias['search_label'] !== '', 'An alias has no searchable characters.');
                $aliasKeys[] = $alias['language'].':'.$alias['search_label'];
            }
            unset($alias);
            $this->unique($aliasKeys, 'normalized aliases');
            $mappingKeys = [];
            foreach ($procedure['mappings'] as $mapping) {
                $this->require(isset($sources[$mapping['source']]), 'Unknown external mapping source.');
                $mappingKeys[] = $sources[$mapping['source']]['system'].':'.$mapping['code'];
                if ($sources[$mapping['source']]['system'] === $sources[$procedure['source']]['system'] && $mapping['relationship'] === 'exact') {
                    $this->require($mapping['code'] === $procedure['source_code'], 'Different codes in the primary source cannot silently become identical.');
                }
                if ($mapping['relationship'] === 'exact') {
                    $mappingKey = self::canonicalJson([$sources[$mapping['source']]['system'], $mapping['code']]);
                    $this->require(! isset($exactOwners[$mappingKey]) || $exactOwners[$mappingKey] === $procedure['canonical_key'], 'An exact external code maps to multiple procedures.');
                    $exactOwners[$mappingKey] = $procedure['canonical_key'];
                }
            }
            $this->unique($mappingKeys, 'external mappings');
            $procedure['search_name'] = self::searchText($procedure['name']);
            $procedure['regions'] = $this->sorted($procedure['regions'], ['key', 'role']);
            $procedure['aliases'] = $this->sorted($procedure['aliases'], ['language', 'search_label']);
            $procedure['mappings'] = $this->sorted($procedure['mappings'], ['source', 'code']);
        }
        unset($procedure);

        $accounted = [];
        foreach (['procedures', 'exclusions', 'quarantines'] as $collection) {
            foreach ($artifact[$collection] as $entry) {
                $this->require(isset($sources[$entry['source']]), 'Unknown coverage source.');
                $accounted[] = $sources[$entry['source']]['system'].':'.$entry['source_code'];
            }
        }
        $this->unique($accounted, 'coverage classifications');
        $this->require(count($accounted) === (int) $artifact['discovered_procedures'], 'Discovered procedures do not reconcile with included, excluded and quarantined records.');
        $counts = $this->counts($artifact);
        foreach ($counts as $field => $count) {
            $this->require($count === (int) $artifact['expected_counts'][$field], 'Artifact count mismatch: '.$field.'.');
        }
        foreach (['modalities', 'regions', 'procedures'] as $collection) {
            $artifact[$collection] = $this->sorted($artifact[$collection], ['canonical_key']);
        }
        $artifact['sources'] = $this->sorted($artifact['sources'], ['key']);
        $artifact['region_relations'] = $this->sorted($artifact['region_relations'], ['parent', 'child', 'relationship']);
        $artifact['exclusions'] = $this->sorted($artifact['exclusions'], ['source', 'source_code']);
        $artifact['quarantines'] = $this->sorted($artifact['quarantines'], ['source', 'source_code']);

        return $artifact;
    }

    public function counts(array $artifact): array
    {
        $counts = [];
        foreach (['sources', 'modalities', 'regions', 'region_relations', 'procedures', 'exclusions', 'quarantines'] as $collection) {
            $counts[$collection] = count($artifact[$collection]);
        }
        $counts['aliases'] = array_sum(array_map(fn ($procedure) => count($procedure['aliases']), $artifact['procedures']));
        $counts['mappings'] = array_sum(array_map(fn ($procedure) => count($procedure['mappings']), $artifact['procedures']));

        return $counts;
    }

    public static function searchText(string $value): string
    {
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', Str::ascii(mb_strtolower($value, 'UTF-8'))));
    }

    public static function canonicalJson(array $value): string
    {
        $sort = function (array $items) use (&$sort): array {
            if (! array_is_list($items)) {
                ksort($items, SORT_STRING);
            }
            foreach ($items as &$item) {
                if (is_array($item)) {
                    $item = $sort($item);
                }
            }
            unset($item);

            return $items;
        };

        return json_encode($sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function validatePricingClassification(array $procedure, array $modality): void
    {
        if ($procedure['pricing_category'] === null) {
            return;
        }
        $allowedCodes = match ($procedure['pricing_category']) {
            'ct_standard' => ['CT'],
            'mr_standard' => ['MR'],
            'us_standard' => ['US'],
            'radiography_standard' => ['CR', 'DX'],
        };
        $this->require($modality['kind'] === 'acquisition' && in_array($modality['dicom_code'], $allowedCodes, true), 'Pricing classification does not match the acquisition modality.');
        $this->require($procedure['clinical_category'] === 'general' && trim($procedure['classification_basis'] ?? '') !== '', 'Owner pricing needs an explicit general-exam classification basis.');
        $allowedContrast = in_array($procedure['pricing_category'], ['ct_standard', 'mr_standard'], true)
            ? ['without', 'with', 'with_and_without'] : ['without', 'not_applicable'];
        $this->require(in_array($procedure['contrast_category'], $allowedContrast, true), 'Unknown or specialized contrast cannot inherit a standard price.');
    }

    private function validateRegionGraph(array $regions, array $relations): void
    {
        $edges = [];
        $indegree = array_fill_keys(array_keys($regions), 0);
        $navigationParents = [];
        $edgeKeys = [];
        foreach ($relations as $relation) {
            $parent = $relation['parent'];
            $child = $relation['child'];
            $this->require(isset($regions[$parent], $regions[$child]) && $parent !== $child, 'Invalid anatomy edge.');
            if ($relation['relationship'] === 'navigation') {
                $this->require(! isset($navigationParents[$child]), 'A navigation region has multiple parents.');
                $navigationParents[$child] = $parent;
            }
            if ($relation['relationship'] === 'composite_member') {
                $this->require($regions[$parent]['kind'] === 'composite', 'Composite membership requires a composite parent.');
            }
            $edgeKeys[] = self::canonicalJson([$parent, $child, $relation['relationship']]);
            $edges[$parent][] = $child;
            $indegree[$child]++;
        }
        $this->unique($edgeKeys, 'anatomy edges');
        $queue = array_keys(array_filter($indegree, fn ($degree) => $degree === 0));
        for ($offset = 0; $offset < count($queue); $offset++) {
            foreach ($edges[$queue[$offset]] ?? [] as $child) {
                if (--$indegree[$child] === 0) {
                    $queue[] = $child;
                }
            }
        }
        $this->require(count($queue) === count($regions), 'Anatomy relationships contain a cycle.');
    }

    private function sorted(array $entries, array $fields): array
    {
        usort($entries, function ($left, $right) use ($fields) {
            foreach ($fields as $field) {
                $comparison = strcmp((string) $left[$field], (string) $right[$field]);
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return 0;
        });

        return $entries;
    }

    private function unique(array $keys, string $description): void
    {
        $this->require(count($keys) === count(array_unique($keys)), 'Duplicate '.$description.'.');
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['catalog' => $message]);
        }
    }
}