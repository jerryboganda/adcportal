<?php

namespace Tests\Unit;

use App\Services\RadiologyCatalog\CatalogNormalizer;
use Illuminate\Validation\ValidationException;
use Tests\Support\RadiologyCatalogFixture;
use Tests\TestCase;

class RadiologyCatalogNormalizationTest extends TestCase
{
    public function test_normalization_is_deterministic_and_preserves_source_fields(): void
    {
        $input = RadiologyCatalogFixture::make();
        $normalizer = new CatalogNormalizer;
        $normalized = $normalizer->normalize($input);
        $reordered = $input;
        foreach (['sources', 'modalities', 'regions', 'procedures', 'region_relations'] as $collection) {
            $reordered[$collection] = array_reverse($reordered[$collection]);
        }

        $this->assertSame(CatalogNormalizer::canonicalJson($normalized), CatalogNormalizer::canonicalJson($normalizer->normalize($reordered)));
        $this->assertSame(CatalogNormalizer::canonicalJson($normalized), CatalogNormalizer::canonicalJson($normalizer->normalize($normalized)));
        $procedure = collect($normalized['procedures'])->firstWhere('canonical_key', 'test:ct-without');
        $this->assertSame($input['procedures'][0]['source_fields'], $procedure['source_fields']);
        $this->assertSame('synthetic ct without', $procedure['search_name']);
    }

    public function test_view_order_is_not_inferred_from_ambiguous_upstream_sequence_fields(): void
    {
        $input = RadiologyCatalogFixture::make();
        $input['procedures'][0]['views'] = ['Synthetic view B', 'Synthetic view A'];
        $input['procedures'][0]['source_fields']['PartSequenceOrder'] = ['1', '1'];

        $result = (new CatalogNormalizer)->normalize($input);
        $procedure = collect($result['procedures'])->firstWhere('canonical_key', 'test:ct-without');
        $this->assertSame(['Synthetic view B', 'Synthetic view A'], $procedure['views']);
        $this->assertSame(['1', '1'], $procedure['source_fields']['PartSequenceOrder']);
    }

    public function test_absent_source_anatomy_is_not_invented(): void
    {
        $input = RadiologyCatalogFixture::make();
        $input['procedures'][0]['regions'] = [];
        $input['procedures'][0]['is_orderable'] = false;

        $result = (new CatalogNormalizer)->normalize($input);
        $procedure = collect($result['procedures'])->firstWhere('canonical_key', 'test:ct-without');
        $this->assertSame([], $procedure['regions']);
        $this->assertFalse($procedure['is_orderable']);

        $input['procedures'][0]['is_orderable'] = true;
        $this->expectException(ValidationException::class);
        (new CatalogNormalizer)->normalize($input);
    }

    public function test_normalizer_rejects_invalid_or_ambiguous_artifacts(): void
    {
        $mutations = [
            'source label rewrite' => fn (&$input) => $input['procedures'][0]['name'] = 'Altered clinical label',
            'unknown source' => fn (&$input) => $input['procedures'][0]['source'] = 'missing',
            'missing license' => fn (&$input) => $input['sources'][0]['license_sha256'] = '',
            'technical modality' => fn (&$input) => $input['modalities'][0]['dicom_code'] = 'SR',
            'unknown anatomy' => fn (&$input) => $input['procedures'][0]['regions'][0]['key'] = 'test:missing',
            'anatomy cycle' => fn (&$input) => $input['region_relations'][] = ['parent' => 'test:brain', 'child' => 'test:head', 'relationship' => 'navigation'],
            'duplicate source identity' => fn (&$input) => $input['procedures'][] = [...$input['procedures'][0], 'canonical_key' => 'test:duplicate'],
            'alias collision' => fn (&$input) => $input['procedures'][0]['aliases'][] = [...$input['procedures'][0]['aliases'][0], 'label' => 'FIXTURE CT WITHOUT'],
            'specialized default price' => fn (&$input) => $input['procedures'][8]['pricing_category'] = 'ct_standard',
            'wrong modality price' => fn (&$input) => $input['procedures'][0]['pricing_category'] = 'mr_standard',
            'unknown contrast price' => fn (&$input) => $input['procedures'][0]['contrast_category'] = 'unknown',
            'contrast route mismatch' => fn (&$input) => $input['procedures'][0]['contrast_routes'] = ['intravenous'],
            'missing fixed side' => fn (&$input) => $input['procedures'][0]['laterality_policy'] = 'fixed',
            'deprecated orderable' => fn (&$input) => $input['procedures'][0]['status'] = 'deprecated',
            'unsupported workflow' => fn (&$input) => $input['procedures'][0]['workflow_supported'] = false,
            'synthetic production source' => fn (&$input) => $input['fixture'] = false,
        ];
        foreach ($mutations as $label => $mutate) {
            $input = RadiologyCatalogFixture::make();
            $mutate($input);
            try {
                (new CatalogNormalizer)->normalize(RadiologyCatalogFixture::withCounts($input));
                $this->fail('Invalid artifact was accepted: '.$label);
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors(), $label);
            }
        }
    }

    public function test_partial_artifact_counts_are_rejected(): void
    {
        $input = RadiologyCatalogFixture::make();
        array_pop($input['procedures']);

        $this->expectException(ValidationException::class);
        (new CatalogNormalizer)->normalize($input);
    }

    public function test_combined_contrast_category_is_independent_of_administration_routes(): void
    {
        $input = RadiologyCatalogFixture::make();
        $input['procedures'][2]['contrast_routes'] = ['oral', 'intravenous'];

        $result = (new CatalogNormalizer)->normalize($input);
        $procedure = collect($result['procedures'])->firstWhere('canonical_key', 'test:ct-combined');
        $this->assertSame('with_and_without', $procedure['contrast_category']);
        $this->assertSame(['oral', 'intravenous'], $procedure['contrast_routes']);
    }
}