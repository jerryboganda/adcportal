<?php

namespace Tests\Feature;

use App\Models\ReportMacro;
use App\Support\ReportMacroLibrary;

/**
 * Clinical snippet text lives in tenant-managed data (see ReportMacroLibrary),
 * never inside a UI component. A radiologist curates personal snippets; the
 * clinic's shared library is governed content.
 */
class ReportMacrosTest extends ApiTestCase
{
    public function test_a_new_clinic_starts_with_the_curated_library(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $macros = $this->actingAs($radiologist)
            ->getJson('/api/v1/reporting/macros')
            ->assertOk()
            ->json('data.macros');

        $this->assertCount(count(ReportMacroLibrary::all()), $macros);
        $this->assertContains('CT Brain — No Acute Intracranial Abnormality', collect($macros)->pluck('name')->all());
        $this->assertTrue(collect($macros)->every(fn (array $m) => $m['scope'] === 'tenant'));
    }

    public function test_macros_can_be_filtered_by_modality_and_searched(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $ctModalityId = (int) $this->tenantService($this->businessA, 'CT-BRAIN-NC')->modality_id;

        $ctMacros = $this->actingAs($radiologist)
            ->getJson("/api/v1/reporting/macros?modalityId={$ctModalityId}")
            ->assertOk()
            ->json('data.macros');

        $codes = collect($ctMacros)->pluck('modalityId')->unique()->all();
        $this->assertContains($ctModalityId, $codes);
        // Modality-agnostic snippets stay available for every modality.
        $this->assertContains(null, $codes);

        $search = $this->actingAs($radiologist)
            ->getJson('/api/v1/reporting/macros?q='.urlencode('BI-RADS'))
            ->assertOk()
            ->json('data.macros');
        $this->assertCount(2, $search);
        $this->assertContains('MG — BI-RADS 1 (Negative)', collect($search)->pluck('name')->all());
    }

    public function test_a_radiologist_curates_personal_snippets_and_the_library_stays_private(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $colleague = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        // Publishing to the shared library is governed content.
        $this->actingAs($radiologist)->postJson('/api/v1/reporting/macros', [
            'name' => 'Shared Snippet',
            'scope' => 'tenant',
            'findings' => 'Findings.',
            'impression' => 'Impression.',
        ])->assertForbidden();

        $created = $this->actingAs($radiologist)->postJson('/api/v1/reporting/macros', [
            'name' => 'My Normal Head',
            'shortcut' => '.myhead',
            'scope' => 'personal',
            'findings' => 'No acute intracranial abnormality.',
            'impression' => 'Normal CT brain.',
        ])->assertCreated();

        $macroId = $created->json('data.macro.id');
        $this->assertSame('personal', $created->json('data.macro.scope'));

        // The author sees it; a colleague does not.
        $mine = $this->actingAs($radiologist)->getJson('/api/v1/reporting/macros?q=myhead')->json('data.macros');
        $this->assertCount(1, $mine);
        $theirs = $this->actingAs($colleague)->getJson('/api/v1/reporting/macros?q='.urlencode('My Normal Head'))->json('data.macros');
        $this->assertCount(0, $theirs);

        // The author may edit it, the colleague may not.
        $this->actingAs($radiologist)->putJson("/api/v1/reporting/macros/{$macroId}", [
            'name' => 'My Normal Head v2',
            'scope' => 'personal',
            'findings' => 'Updated findings.',
            'impression' => 'Normal CT brain.',
        ])->assertOk();

        $this->actingAs($colleague)->putJson("/api/v1/reporting/macros/{$macroId}", [
            'name' => 'Hijacked',
            'findings' => 'x',
            'impression' => 'y',
        ])->assertForbidden();
    }

    public function test_using_a_macro_counts_usage_and_archiving_retires_it_without_deleting(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $macro = ReportMacro::where('business_id', $this->businessA->id)->firstOrFail();

        $this->actingAs($radiologist)
            ->postJson("/api/v1/reporting/macros/{$macro->id}/use")
            ->assertOk()
            ->assertJsonPath('data.usageCount', 1);

        $this->assertSame(1, $macro->fresh()->usage_count);

        // Archival, not deletion: a report drafted from it keeps its provenance.
        $this->actingAs($this->adminA)
            ->deleteJson("/api/v1/reporting/macros/{$macro->id}")
            ->assertOk();

        $this->assertTrue($macro->fresh()->is_archived);
        $this->assertNull($macro->fresh()->deleted_at);
        $this->assertNotContains(
            $macro->name,
            collect($this->actingAs($radiologist)->getJson('/api/v1/reporting/macros')->json('data.macros'))->pluck('name')->all(),
        );
    }

    public function test_a_clinics_macros_are_never_visible_to_another_clinic(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        ReportMacro::create([
            'name' => 'Beta Secret Snippet',
            'findings' => 'beta',
            'impression' => 'beta',
            'scope' => 'tenant',
            'business_id' => $this->businessB->id,
            'created_by' => $this->businessB->created_by,
        ]);

        $names = collect($this->actingAs($radiologist)->getJson('/api/v1/reporting/macros')->json('data.macros'))->pluck('name');
        $this->assertNotContains('Beta Secret Snippet', $names->all());

        // And a foreign macro id is simply not addressable.
        $foreign = ReportMacro::where('business_id', $this->businessB->id)->firstOrFail();
        $this->actingAs($radiologist)->putJson("/api/v1/reporting/macros/{$foreign->id}", [
            'name' => 'Hijack',
            'findings' => 'x',
            'impression' => 'y',
        ])->assertNotFound();
    }
}
