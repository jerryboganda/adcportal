<?php

namespace Tests\Feature;

use App\Models\ReportingPreference;
use App\Models\ReportingSavedView;

/**
 * A radiologist's reporting setup belongs to the USER, not to a browser.
 *
 * It lived in localStorage, so it vanished on any other workstation — and in a
 * reading room that is most days. These tests pin the two things that make
 * moving it server-side safe: it is scoped to one user inside one clinic, and
 * it can never be used to reach another user's or another clinic's rows.
 */
class ReportingPreferencesTest extends ApiTestCase
{
    public function test_defaults_are_returned_before_anything_is_saved(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $response = $this->actingAs($radiologist)->getJson('/api/v1/reporting/preferences')->assertOk();

        $this->assertSame('en-US', $response->json('data.preferences.dictationLanguage'));
        $this->assertSame('browser', $response->json('data.preferences.dictationProvider'));
        $this->assertTrue($response->json('data.preferences.templateAutoload'));
        $this->assertSame('unreported', $response->json('data.preferences.defaultTab'));
        $this->assertSame([], $response->json('data.views'));

        // Nothing was written just by reading.
        $this->assertSame(0, ReportingPreference::count());
    }

    public function test_preferences_round_trip_and_follow_the_user(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $this->actingAs($radiologist)->putJson('/api/v1/reporting/preferences', [
            'dictationLanguage' => 'ur-PK',
            'dictationProvider' => 'server',
            'templateAutoload' => false,
            'defaultTab' => 'drafts',
        ])->assertOk()
            ->assertJsonPath('data.preferences.dictationLanguage', 'ur-PK')
            ->assertJsonPath('data.preferences.defaultTab', 'drafts');

        // A different session (same user) reads the saved values back.
        $this->actingAs($radiologist)->getJson('/api/v1/reporting/preferences')
            ->assertOk()
            ->assertJsonPath('data.preferences.dictationLanguage', 'ur-PK')
            ->assertJsonPath('data.preferences.dictationProvider', 'server')
            ->assertJsonPath('data.preferences.templateAutoload', false);

        // A partial update leaves the other preferences alone.
        $this->actingAs($radiologist)->putJson('/api/v1/reporting/preferences', [
            'templateAutoload' => true,
        ])->assertOk();

        $this->actingAs($radiologist)->getJson('/api/v1/reporting/preferences')
            ->assertOk()
            ->assertJsonPath('data.preferences.dictationLanguage', 'ur-PK')
            ->assertJsonPath('data.preferences.templateAutoload', true);

        // One row per user, not one per save.
        $this->assertSame(1, ReportingPreference::where('business_id', $this->businessA->id)->count());
    }

    public function test_an_unsupported_value_is_rejected_rather_than_stored(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        // The UI cannot render a language it does not offer, so the API refuses
        // to accept one from a hand-crafted request either.
        $this->actingAs($radiologist)->putJson('/api/v1/reporting/preferences', [
            'dictationLanguage' => 'xx-YY',
        ])->assertStatus(422);

        $this->actingAs($radiologist)->putJson('/api/v1/reporting/preferences', [
            'defaultTab' => 'everything',
        ])->assertStatus(422);

        $this->actingAs($radiologist)->putJson('/api/v1/reporting/preferences', [])
            ->assertStatus(422);

        $this->assertSame(0, ReportingPreference::count());
    }

    public function test_two_radiologists_each_keep_their_own_setup(): void
    {
        $first = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $second = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $this->actingAs($first)->putJson('/api/v1/reporting/preferences', ['defaultTab' => 'priority'])->assertOk();
        $this->actingAs($second)->putJson('/api/v1/reporting/preferences', ['defaultTab' => 'drafts'])->assertOk();

        $this->actingAs($first)->getJson('/api/v1/reporting/preferences')
            ->assertOk()->assertJsonPath('data.preferences.defaultTab', 'priority');

        $this->actingAs($second)->getJson('/api/v1/reporting/preferences')
            ->assertOk()->assertJsonPath('data.preferences.defaultTab', 'drafts');
    }

    // ==================== saved views ====================

    public function test_a_saved_view_round_trips_with_its_filters(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $created = $this->actingAs($radiologist)->postJson('/api/v1/reporting/views', [
            'name' => 'STAT CT chest',
            'filters' => [
                'tab' => 'priority',
                'priority' => 'stat',
                'modalityId' => (int) $this->tenantService($this->businessA, 'CT-BRAIN-NC')->modality_id,
                'sort' => 'oldest',
            ],
        ])->assertOk()->json('data.view');

        $this->assertSame('STAT CT chest', $created['name']);
        $this->assertSame('stat', $created['filters']['priority']);
        $this->assertSame('oldest', $created['filters']['sort']);

        // It is listed for its owner…
        $views = $this->actingAs($radiologist)->getJson('/api/v1/reporting/preferences')->json('data.views');
        $this->assertCount(1, $views);

        // …and updating it replaces the filters.
        $this->actingAs($radiologist)->putJson("/api/v1/reporting/views/{$created['id']}", [
            'name' => 'STAT CT chest (urgent too)',
            'filters' => ['tab' => 'priority', 'sort' => 'newest'],
        ])->assertOk()->assertJsonPath('data.view.name', 'STAT CT chest (urgent too)');

        $this->actingAs($radiologist)->deleteJson("/api/v1/reporting/views/{$created['id']}")->assertOk();
        $this->assertSame(0, ReportingSavedView::count());
    }

    public function test_saving_the_same_name_updates_instead_of_duplicating(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $first = $this->actingAs($radiologist)->postJson('/api/v1/reporting/views', [
            'name' => 'My list',
            'filters' => ['tab' => 'unreported'],
        ])->assertOk()->json('data.view.id');

        $second = $this->actingAs($radiologist)->postJson('/api/v1/reporting/views', [
            'name' => 'My list',
            'filters' => ['tab' => 'drafts'],
        ])->assertOk()->json('data.view.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, ReportingSavedView::count());
    }

    public function test_an_update_with_nothing_to_change_is_refused(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $viewId = $this->actingAs($radiologist)->postJson('/api/v1/reporting/views', [
            'name' => 'My list',
            'filters' => ['tab' => 'unreported'],
        ])->json('data.view.id');

        // An empty body is a caller bug; answering 200 would look like a save.
        $this->actingAs($radiologist)
            ->putJson("/api/v1/reporting/views/{$viewId}", [])
            ->assertStatus(422);
    }

    public function test_another_radiologists_view_cannot_be_read_or_deleted(): void
    {
        $owner = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $colleague = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $viewId = $this->actingAs($owner)->postJson('/api/v1/reporting/views', [
            'name' => 'Private list',
            'filters' => ['tab' => 'unreported'],
        ])->json('data.view.id');

        // Not listed for the colleague…
        $this->assertSame(
            [],
            $this->actingAs($colleague)->getJson('/api/v1/reporting/preferences')->json('data.views')
        );

        // …and not addressable by id: a 404, so its existence is not confirmed.
        $this->actingAs($colleague)->putJson("/api/v1/reporting/views/{$viewId}", [
            'filters' => ['tab' => 'all'],
        ])->assertNotFound();

        $this->actingAs($colleague)->deleteJson("/api/v1/reporting/views/{$viewId}")->assertNotFound();

        $this->assertSame(1, ReportingSavedView::count());
    }

    public function test_another_clinics_view_cannot_be_touched(): void
    {
        $ownerA = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $ownerB = $this->makeStaff($this->businessB, $this->adminB, 'radiologist');

        $viewId = $this->actingAs($ownerB)->postJson('/api/v1/reporting/views', [
            'name' => 'Beta list',
            'filters' => ['tab' => 'all'],
        ])->json('data.view.id');

        $this->actingAs($ownerA)->deleteJson("/api/v1/reporting/views/{$viewId}")->assertNotFound();
        $this->actingAs($ownerA)->putJson("/api/v1/reporting/views/{$viewId}", [
            'filters' => ['tab' => 'all'],
        ])->assertNotFound();

        $this->assertSame(1, ReportingSavedView::count());
    }

    public function test_a_setup_is_keyed_by_clinic_so_a_second_clinic_starts_clean(): void
    {
        // A radiologist who also works at another site: a "CT chest" view means
        // something different in each clinic, so the two setups must not merge
        // (and the unique key allows exactly one row per user per clinic).
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $this->actingAs($radiologist)->putJson('/api/v1/reporting/preferences', ['defaultTab' => 'priority'])->assertOk();

        $this->assertNull(
            ReportingPreference::forClinic($this->businessB->id)->where('user_id', $radiologist->id)->first(),
            'clinic B must not see clinic A\'s row for the same person',
        );

        $other = ReportingPreference::forUser($this->businessB->id, (int) $radiologist->id);
        $this->assertSame('unreported', $other->default_tab);
        $this->assertSame($this->businessB->id, $other->business_id);

        $this->assertSame(1, ReportingPreference::count());
    }

    public function test_preferences_require_reporting_rights(): void
    {
        $receptionist = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        $this->actingAs($receptionist)->getJson('/api/v1/reporting/preferences')->assertForbidden();
        $this->actingAs($receptionist)->putJson('/api/v1/reporting/preferences', ['defaultTab' => 'all'])->assertForbidden();
        $this->actingAs($receptionist)->postJson('/api/v1/reporting/views', [
            'name' => 'Sneaky',
            'filters' => ['tab' => 'all'],
        ])->assertForbidden();

        $this->assertSame(0, ReportingSavedView::count());
    }
}
