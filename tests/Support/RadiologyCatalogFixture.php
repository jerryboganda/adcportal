<?php

namespace Tests\Support;

final class RadiologyCatalogFixture
{
    public static function make(): array
    {
        $artifact = [
            'schema_version' => 1,
            'release_key' => 'test:radiology-v1',
            'fixture' => true,
            'sources' => [[
                'key' => 'synthetic',
                'system' => 'TEST',
                'version' => '1',
                'release_date' => '2026-09-20',
                'url' => 'https://example.invalid/synthetic-catalog',
                'license_url' => 'https://example.invalid/test-fixture',
                'license_sha256' => hash('sha256', 'synthetic fixture notice'),
                'attribution' => 'Synthetic test fixture. Not a clinical terminology dataset.',
                'sha256' => hash('sha256', 'synthetic catalog v1'),
                'retrieved_at' => '2026-09-20T00:00:00+00:00',
            ]],
            'modalities' => [],
            'regions' => [],
            'region_relations' => [
                ['parent' => 'test:head', 'child' => 'test:brain', 'relationship' => 'navigation'],
                ['parent' => 'test:spine', 'child' => 'test:cervical', 'relationship' => 'navigation'],
            ],
            'procedures' => [],
            'exclusions' => [],
            'quarantines' => [],
        ];
        foreach (['CT', 'MR', 'US', 'DX'] as $code) {
            $artifact['modalities'][] = [
                ...self::identity('test:'.strtolower($code), 'MOD-'.$code, 'Synthetic '.$code),
                'kind' => 'acquisition',
                'dicom_code' => $code,
                'acquisition_codes' => [$code],
            ];
        }
        foreach (['head', 'brain', 'spine', 'cervical'] as $code) {
            $artifact['regions'][] = [
                ...self::identity('test:'.$code, 'REGION-'.strtoupper($code), 'Synthetic '.ucfirst($code)),
                'kind' => 'structure',
            ];
        }
        $definitions = [
            ['ct-without', 'ct', 'without', 'ct_standard', 'general'],
            ['ct-with', 'ct', 'with', 'ct_standard', 'general'],
            ['ct-combined', 'ct', 'with_and_without', 'ct_standard', 'general'],
            ['mr-without', 'mr', 'without', 'mr_standard', 'general'],
            ['mr-with', 'mr', 'with', 'mr_standard', 'general'],
            ['mr-combined', 'mr', 'with_and_without', 'mr_standard', 'general'],
            ['us-general', 'us', 'not_applicable', 'us_standard', 'general'],
            ['dx-general', 'dx', 'not_applicable', 'radiography_standard', 'general'],
            ['ct-special', 'ct', 'with', null, 'perfusion'],
        ];
        foreach ($definitions as [$key, $modality, $contrast, $pricing, $category]) {
            $artifact['procedures'][] = [
                ...self::identity('test:'.$key, 'EXAM-'.strtoupper($key), 'Synthetic '.$key),
                'short_name' => null,
                'modality' => 'test:'.$modality,
                'status' => 'active',
                'is_orderable' => true,
                'workflow_supported' => true,
                'clinical_category' => $category,
                'contrast_category' => $contrast,
                'contrast_routes' => in_array($contrast, ['with', 'with_and_without'], true) ? ['intravenous'] : [],
                'contrast_agents' => [],
                'laterality_policy' => 'not_applicable',
                'fixed_laterality' => null,
                'views' => [],
                'attributes' => [],
                'pricing_category' => $pricing,
                'classification_basis' => $pricing ? 'Explicit synthetic general-exam classification for testing only.' : null,
                'regions' => [['key' => $modality === 'mr' ? 'test:cervical' : 'test:brain', 'role' => 'primary']],
                'aliases' => [[
                    'label' => 'Fixture '.$key,
                    'language' => 'en',
                    'kind' => 'application',
                    'provenance' => 'Synthetic test alias.',
                ]],
                'mappings' => [],
            ];
        }

        return self::withCounts($artifact);
    }

    public static function withCounts(array $artifact): array
    {
        $counts = [];
        foreach (['sources', 'modalities', 'regions', 'region_relations', 'procedures', 'exclusions', 'quarantines'] as $collection) {
            $counts[$collection] = count($artifact[$collection]);
        }
        $counts['aliases'] = array_sum(array_map(fn ($procedure) => count($procedure['aliases']), $artifact['procedures']));
        $counts['mappings'] = array_sum(array_map(fn ($procedure) => count($procedure['mappings']), $artifact['procedures']));
        $artifact['expected_counts'] = $counts;
        $artifact['discovered_procedures'] = $counts['procedures'] + $counts['exclusions'] + $counts['quarantines'];

        return $artifact;
    }

    private static function identity(string $key, string $code, string $name): array
    {
        return [
            'canonical_key' => $key,
            'source' => 'synthetic',
            'source_code' => $code,
            'name' => $name,
            'source_code_field' => 'CODE',
            'source_name_field' => 'DISPLAY',
            'source_fields' => ['CODE' => $code, 'DISPLAY' => $name, 'NOTICE' => 'Synthetic test data only.'],
        ];
    }
}