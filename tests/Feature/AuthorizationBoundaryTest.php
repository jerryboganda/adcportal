<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Location;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;

/**
 * The Phase 2 authorization fixes, asserted at the boundary each one closes.
 *
 * Four separate holes in the same class: a foreign key that was not scoped, a
 * read that was not gated, a read that was gated differently from its own write,
 * and a commercial payload that reached a role with no entitlement to it.
 */
class AuthorizationBoundaryTest extends ApiTestCase
{
    // ------------------------------------------------------- unscoped FKs

    public function test_an_invoice_line_cannot_cite_another_tenants_service(): void
    {
        $foreignService = Service::where('business_id', $this->businessB->id)->firstOrFail();
        $ownService = Service::where('code', 'US-ABD-PEL')->where('business_id', $this->businessA->id)->firstOrFail();

        // Guard against the test silently testing nothing.
        $this->assertNotSame(
            (int) $foreignService->business_id,
            (int) $ownService->business_id,
            'The fixture must span two tenants for this to mean anything.'
        );

        // The booking actor needs `appointment create`, which the `billing`
        // bundle deliberately does not carry (it is a POS role, not a
        // scheduling one), so the study is booked by the clinic owner.
        $study = $this->bookStudy($ownService, $this->adminA);

        $this->actingAs($this->adminA)->postJson("/api/v1/studies/{$study}/invoices", [
            'items' => [[
                'serviceId' => $foreignService->id,
                'description' => 'Smuggled line item',
                'quantity' => 1,
                'unitPrice' => 100,
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.serviceId');
    }

    /** A real study in the active tenant, so the invoice route has its subject. */
    private function bookStudy(Service $service, User $actor): string
    {
        $response = $this->actingAs($actor)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Authorization Fixture', 'gender' => 'female'],
            'serviceId' => $service->id,
            'date' => now()->toDateString(),
            'time' => '11:00 AM',
            'priority' => 'routine',
        ]);

        if ($response->status() !== 201) {
            $this->fail('BOOKING FAILED ['.$response->status().']: '.substr($response->getContent(), 0, 600));
        }

        return (string) $response->json('data.study.id');
    }

    public function test_an_imaging_suite_cannot_cite_another_tenants_location(): void
    {
        $foreign = Location::create([
            'name' => 'Foreign Facility '.uniqid(),
            // `locations.address` is NOT NULL, unlike the rest of the facade row.
            'address' => '1 Example Road, Springfield',
            'business_id' => $this->businessB->id,
            'created_by' => $this->adminB->id,
        ]);

        $modalityId = (int) \App\Models\Modality::where('business_id', $this->businessA->id)->value('id');

        $this->actingAs($this->adminA)->postJson('/api/v1/rooms', [
            'name' => 'Suite '.uniqid(),
            'modalityId' => $modalityId,
            'locationId' => $foreign->id,
        ])->assertStatus(422)->assertJsonValidationErrors('locationId');

        $this->assertDatabaseMissing('rooms', ['name' => 'Suite ']);
    }

    // ------------------------------------------------------- ungated reads

    public function test_the_clinic_profile_is_not_readable_without_a_setting_permission(): void
    {
        // A radiologist's bundle carries no administration permission, yet the
        // ungated read returned PNRA/PMC/tax ids and the billing configuration.
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $this->actingAs($radiologist)->getJson('/api/v1/clinic')->assertForbidden();
        $this->actingAs($this->adminA)->getJson('/api/v1/clinic')->assertOk();
    }

    public function test_print_settings_read_and_write_need_the_same_permission(): void
    {
        // The invariant is PARITY, not "a receptionist may open the panel": the
        // read used to ask for `clinic manage` while the write asked for
        // ['print settings manage','setting manage'], so the two disagreed. The
        // `receptionist` bundle holds neither print grant (it gets `receipt
        // print` and `label print`, not the settings panel), so the honest
        // assertion is that the same role is refused by BOTH verbs.
        $reception = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        $this->actingAs($reception)->getJson('/api/v1/settings/printing')->assertForbidden();
        $this->actingAs($reception)->putJson('/api/v1/settings/printing', [
            'paper' => 'a4',
        ])->assertForbidden();

        // …and a role that does hold it reads the panel, while a clinical role
        // that holds neither is still refused.
        $this->actingAs($this->adminA)->getJson('/api/v1/settings/printing')->assertOk();

        $technologist = $this->makeStaff($this->businessA, $this->adminA, 'technologist');
        $this->actingAs($technologist)->getJson('/api/v1/settings/printing')->assertForbidden();
    }

    // --------------------------------------- commercial payload entitlement

    public function test_inventory_commercial_fields_are_omitted_for_a_role_that_may_only_acquire(): void
    {
        // `study acquire` legitimately receives the catalogue for the contrast
        // picker. It must not receive the clinic's purchasing prices with it.
        $technologist = $this->makeStaff($this->businessA, $this->adminA, 'technologist');
        $this->assertTrue(
            \App\Services\TenantAuthorizer::allows($technologist, 'study acquire', $this->businessA->id)
        );

        $mine = $this->actingAs($technologist)->getJson('/api/v1/bootstrap')->assertOk()->json('data.inventoryItems');
        $this->assertNotEmpty($mine, 'The contrast picker needs the catalogue, so it must still arrive.');

        foreach ($mine as $item) {
            $this->assertNull($item['sellingPrice'], 'A technologist must not receive the selling price.');
            $this->assertNull($item['unitCost'], 'A technologist must not receive the unit cost.');
            $this->assertSame([], $item['batches'], 'Lot numbers are stock-admin detail.');
            $this->assertSame('', $item['supplier']);
            $this->assertSame('', $item['storageLocation']);
        }

        // …and a stock admin still gets the whole thing.
        $admin = $this->actingAs($this->adminA)->getJson('/api/v1/bootstrap')->assertOk()->json('data.inventoryItems');
        $this->assertNotEmpty($admin);
        $this->assertIsNumeric($admin[0]['sellingPrice']);
    }

    // ------------------------------------------------------ public signup

    public function test_registration_can_be_closed_by_configuration(): void
    {
        config(['ris.registration.enabled' => false]);

        $this->postJson('/api/v1/register', [
            'clinic_name' => 'Should Not Exist',
            'name' => 'Owner',
            'email' => 'closed.door@blocked.test',
            'password' => 'R1s!T3st#2026x',
        ])->assertStatus(403)->assertJsonPath('error', 'registration.closed');

        $this->assertDatabaseMissing('businesses', ['name' => 'Should Not Exist']);
    }

    public function test_registration_is_refused_once_too_many_tenants_are_pending(): void
    {
        // A signup wave must be bounded, not merely rate-limited: 6/min/IP still
        // fills the operator's activation queue with tenants that can never pass
        // EnsureTenantActive.
        //
        // The cap counts tenants sitting in a PENDING status, so the fixtures
        // (which provision as trialing) already occupy the queue. Flipping them
        // to active here would have emptied it and asserted the opposite of what
        // the guard does.
        config(['ris.registration.max_pending_tenants' => 1]);

        $pending = Business::query()
            ->whereIn('subscription_status', (array) config('ris.registration.pending_statuses', ['provisioning', 'trialing']))
            ->count();
        $this->assertGreaterThanOrEqual(1, $pending, 'The queue must already hold a pending tenant for this to mean anything.');

        $this->postJson('/api/v1/register', [
            'clinic_name' => 'One Too Many',
            'name' => 'Owner',
            'email' => 'at.capacity@blocked.test',
            'password' => 'R1s!T3st#2026x',
        ])->assertStatus(503)->assertJsonPath('error', 'registration.at_capacity');

        $this->assertDatabaseMissing('businesses', ['name' => 'One Too Many']);
    }
}
