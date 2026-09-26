<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Customer;
use App\Models\Referrer;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Three boundaries that were open and are now closed, each asserted at the
 * boundary itself so a regression fails here rather than in a clinic.
 *
 * They live in one file because they are one class of defect: a request
 * identifier or a personal message crossing a tenant or user line.
 */
class CrossBoundaryLeakTest extends ApiTestCase
{
    // ---------------------------------------------------------------- referrer

    /**
     * A booking must not be able to cite another clinic's referring doctor.
     *
     * `referrerId` was validated as a bare `integer` while every sibling foreign
     * key in the same payload was tenant-scoped, and the study response eagerly
     * loaded `referrer` — so the write was accepted and the other clinic's
     * doctor's name, clinic, email and phone were handed straight back.
     */
    public function test_a_booking_cannot_cite_another_tenants_referrer(): void
    {
        $foreign = Referrer::create([
            'name' => 'Dr. Foreign Clinic',
            'clinic' => 'Some Other Imaging Centre',
            'email' => 'foreign@other-clinic.test',
            'phone' => '0300-0000000',
            'is_active' => true,
            'business_id' => $this->businessB->id,
            'created_by' => $this->adminB->id,
        ]);

        $service = Service::where('code', 'US-ABD-PEL')->where('business_id', $this->businessA->id)->firstOrFail();
        $receptionist = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        $response = $this->actingAs($receptionist)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Cross Tenant Referrer', 'gender' => 'male'],
            'serviceId' => $service->id,
            'referrerId' => $foreign->id,
            'date' => now()->toDateString(),
            'time' => '11:00 AM',
            'priority' => 'routine',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('referrerId');

        $this->assertDatabaseMissing('appointments', ['referrer_id' => $foreign->id]);
    }

    /** The same hole existed on the manual-report path; it must close there too. */
    public function test_a_manual_report_cannot_cite_another_tenants_referrer(): void
    {
        $foreign = Referrer::create([
            'name' => 'Dr. Foreign Report',
            'clinic' => 'Another Imaging Centre',
            'email' => 'foreign2@other-clinic.test',
            'is_active' => true,
            'business_id' => $this->businessB->id,
            'created_by' => $this->adminB->id,
        ]);

        $service = Service::where('code', 'US-ABD-PEL')->where('business_id', $this->businessA->id)->firstOrFail();

        $this->actingAs($this->adminA)->postJson('/api/v1/reporting/reports/manual', [
            'newPatient' => ['name' => 'Cross Tenant Report', 'gender' => 'male'],
            'serviceId' => $service->id,
            'referrerId' => $foreign->id,
            'date' => now()->toDateString(),
            'priority' => 'routine',
        ])->assertStatus(422)->assertJsonValidationErrors('referrerId');
    }

    public function test_a_booking_can_still_cite_its_own_tenants_referrer(): void
    {
        // The tenancy rule must not have cost the feature it protects.
        $own = Referrer::create([
            'name' => 'Dr. Own Clinic',
            'clinic' => 'This Imaging Centre',
            'is_active' => true,
            'business_id' => $this->businessA->id,
            'created_by' => $this->adminA->id,
        ]);

        $service = Service::where('code', 'US-ABD-PEL')->where('business_id', $this->businessA->id)->firstOrFail();
        $receptionist = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        $this->actingAs($receptionist)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Own Tenant Referrer', 'gender' => 'male'],
            'serviceId' => $service->id,
            'referrerId' => $own->id,
            'date' => now()->toDateString(),
            'time' => '11:00 AM',
            'priority' => 'routine',
        ])->assertCreated();

        $this->assertDatabaseHas('appointments', ['referrer_id' => $own->id]);
    }

    // ----------------------------------------------------------- notifications

    /**
     * A notification addressed to one person is nobody else's.
     *
     * `target_user_id` was passed in and dropped on the floor (no column, not
     * fillable), so "assigned to you — <patient name>" was written as a
     * clinic-wide row and appeared in every staff member's feed and badge.
     */
    public function test_a_targeted_notification_is_invisible_to_a_colleague(): void
    {
        $assigned = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $colleague = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $mine = AppNotification::create([
            'title' => 'Study Assigned for Reporting',
            'message' => 'Confidential Patient (MRN-1) has been assigned to you.',
            'category' => 'workflow',
            'priority' => 'normal',
            'patient_name' => 'Confidential Patient',
            'business_id' => $this->businessA->id,
            'target_user_id' => $assigned->id,
        ]);

        $theirs = AppNotification::create([
            'title' => 'Study Assigned for Reporting',
            'message' => 'Someone Else (MRN-2) has been assigned to you.',
            'category' => 'workflow',
            'priority' => 'normal',
            'patient_name' => 'Someone Else',
            'business_id' => $this->businessA->id,
            'target_user_id' => $colleague->id,
        ]);

        $seenByAssigned = $this->actingAs($assigned)
            ->getJson('/api/v1/notifications')->assertOk()->json('data.notifications');

        // Ids cross the wire as strings (ApiShape::id), so both sides are
        // compared as strings; the invariant is visibility, not set equality,
        // because the tenant fixture may already carry rows of its own.
        $seenIds = array_map('strval', array_column($seenByAssigned, 'id'));

        $this->assertContains((string) $mine->id, $seenIds, 'The addressee must see their own assignment.');
        $this->assertNotContains((string) $theirs->id, $seenIds, 'A colleague must not see my assignment.');
        $this->assertStringNotContainsString('Someone Else', json_encode($seenByAssigned));
    }

    /** An untargeted alert (STAT, critical result, low stock) stays clinic-wide. */
    public function test_an_untargeted_alert_stays_visible_to_everyone_in_the_tenant(): void
    {
        $one = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');
        $two = $this->makeStaff($this->businessA, $this->adminA, 'technician');

        $alert = AppNotification::create([
            'title' => 'CRITICAL FINDING',
            'message' => 'A critical finding was reported.',
            'category' => 'workflow',
            'priority' => 'critical',
            'business_id' => $this->businessA->id,
            'target_user_id' => null,
        ]);

        foreach ([$one, $two] as $staff) {
            $ids = array_map('strval', array_column(
                $this->actingAs($staff)->getJson('/api/v1/notifications')->assertOk()->json('data.notifications'),
                'id'
            ));
            $this->assertContains((string) $alert->id, $ids, 'A clinic-wide alert must reach every member of staff.');
        }
    }

    /** `mark-read` reports what it changed, not what it was asked to change. */
    public function test_mark_read_reports_the_rows_it_actually_changed(): void
    {
        $assigned = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $colleague = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $theirs = AppNotification::create([
            'title' => 'Assigned to someone else',
            'message' => 'x',
            'business_id' => $this->businessA->id,
            'target_user_id' => $colleague->id,
        ]);

        $mine = AppNotification::create([
            'title' => 'Assigned to me',
            'message' => 'x',
            'business_id' => $this->businessA->id,
            'target_user_id' => $assigned->id,
        ]);

        $response = $this->actingAs($assigned)
            ->postJson('/api/v1/notifications/mark-read', ['ids' => [$mine->id, $theirs->id]])
            ->assertOk();

        // Two were requested; one was mine to change. The old code answered 2.
        $this->assertSame(1, $response->json('data.marked'));
        $this->assertTrue((bool) $mine->fresh()->is_read);
        $this->assertFalse((bool) $theirs->fresh()->is_read, "Another user's notification must not be mutated.");
    }

    /** "Clear all" clears what I can see — never the clinic's alert trail. */
    public function test_clear_all_does_not_delete_a_colleagues_notification(): void
    {
        $assigned = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $colleague = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $theirs = AppNotification::create([
            'title' => 'Assigned to someone else', 'message' => 'x',
            'business_id' => $this->businessA->id, 'target_user_id' => $colleague->id,
        ]);

        $this->actingAs($assigned)->deleteJson('/api/v1/notifications')->assertOk();

        $this->assertDatabaseHas('ris_app_notifications', ['id' => $theirs->id]);
    }

    // --------------------------------------------------------------- timezone

    /**
     * The clinic's day is the clinic's day.
     *
     * The app ran in UTC while the clinic is UTC+5, so between 00:00 and 05:00
     * local `now()->toDateString()` was YESTERDAY: "today" matched no study on
     * the reception desk or the tech worklist, bookings defaulted to the wrong
     * day, and the shift-closing sheet was filed under the wrong date.
     */
    public function test_the_application_timezone_is_the_clinics_not_utc(): void
    {
        $this->assertSame('Asia/Karachi', config('app.timezone'));
    }

    public function test_the_calendar_day_rolls_over_at_local_midnight_not_utc_midnight(): void
    {
        // 20:00 UTC on the 25th is 01:00 on the 26th in Lahore. A UTC app calls
        // that the 25th.
        Carbon::setTestNow(Carbon::parse('2026-09-25 20:00:00', 'UTC'));

        try {
            $this->assertSame('2026-09-26', now()->toDateString());
            $this->assertSame('2026-09-26', now()->timezone('Asia/Karachi')->toDateString());

            // …and the last five minutes of the local day are still that day.
            Carbon::setTestNow(Carbon::parse('2026-09-25 18:59:00', 'UTC'));
            $this->assertSame('2026-09-25', now()->toDateString());
        } finally {
            Carbon::setTestNow();
        }
    }
}
