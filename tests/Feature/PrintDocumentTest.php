<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Referrer;
use App\Models\RadiologyReport;
use App\Models\Service;
use App\Support\Print\PrintArtifactRegistry;

/**
 * The print document model, for EVERY artifact in the registry.
 *
 * The loop over `PrintArtifactRegistry::all()` is deliberate: printing used to
 * be a per-screen implementation, so a new screen could quietly ship without a
 * paper profile, without a permission and without a test. Adding an artifact
 * now fails this suite until it produces a real payload.
 *
 * It also proves the two properties that are easy to lose and impossible to
 * notice: printing never writes a business record, and a tenant can never print
 * another tenant's document.
 */
class PrintDocumentTest extends ApiTestCase
{
    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function bookingWithInvoice(): array
    {
        $svc = Service::where('code', 'US-ABD-PEL')->where('business_id', $this->businessA->id)->firstOrFail();
        $receptionist = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        $booking = $this->actingAs($receptionist)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Print Model Patient', 'gender' => 'female', 'age' => 44],
            'serviceId' => $svc->id,
            'date' => now()->toDateString(),
            'time' => '11:00 AM',
            'priority' => 'routine',
        ])->assertCreated()->json('data');

        return [$booking['study'], $booking['invoice']];
    }

    public function test_every_registered_artifact_produces_a_complete_payload(): void
    {
        [$study, $invoice] = $this->bookingWithInvoice();
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $report = $this->actingAs($radiologist)->postJson("/api/v1/studies/{$study['id']}/reports", [
            'findings' => 'No acute abnormality.',
            'impression' => 'Normal study.',
        ])->assertOk()->json('data.report');

        $referrer = Referrer::create([
            'name' => 'Dr Settlement Test',
            'specialty' => 'Neurology',
            'clinic' => 'Test Clinic',
            'business_id' => $this->businessA->id,
            'created_by' => $this->businessA->created_by,
        ]);

        $ids = [
            'invoice' => $invoice['id'],
            'receipt' => $invoice['id'],
            'report' => $report['id'],
            'token' => $study['id'],
            'label' => $study['id'],
            'manifest' => now()->toDateString(),
            'fee-schedule' => 'current',
            'doctor-settlement' => $referrer->id,
            'shift-closing' => now()->toDateString(),
        ];

        $admin = $this->adminA;

        foreach (PrintArtifactRegistry::keys() as $artifact) {
            $this->assertArrayHasKey($artifact, $ids, "Artifact [{$artifact}] has no fixture — add one to this test.");

            $response = $this->actingAs($admin)->getJson("/api/v1/print/{$artifact}/{$ids[$artifact]}");

            $response->assertOk();
            $document = $response->json('data.document');

            $this->assertSame($artifact, $document['artifact']);
            $this->assertNotEmpty($document['documentKey'], "{$artifact} has no document key");
            $this->assertNotEmpty($document['artifactLabel']);
            $this->assertNotEmpty($document['branding']['name'], "{$artifact} printed without tenant branding");
            $this->assertArrayHasKey($document['paper'], ['a4' => 1, 'thermal80' => 1, 'label' => 1], "{$artifact} chose an unknown paper");
            $this->assertStringContainsString('@page', $document['pageCss']);
            $this->assertStringContainsString('.pd-doc', $document['documentCss'], "{$artifact} shipped no document stylesheet");
            // The stylesheet travels to the browser inside the payload and is
            // injected with `style.textContent`, so it must be BARE CSS: a
            // `<style>` wrapper would be injected as text and the CSS parser
            // would drop the first rule (selector `<style> .pd-doc`) and let the
            // stray `</style>` swallow `.pd-paper` — the paper geometry itself.
            $this->assertStringNotContainsString('<style', $document['documentCss'], "{$artifact} shipped a wrapped stylesheet");
            $this->assertStringNotContainsString('</style', $document['documentCss'], "{$artifact} shipped a wrapped stylesheet");
            $this->assertNotEmpty($document['render']['contentWidthMm']);

            // Nothing may leak a PHP/JS null into paper.
            $html = view('print.document', ['document' => $document])->render();
            foreach (['undefined', 'NaN', '[object Object]'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $html, "{$artifact} printed [{$forbidden}]");
            }
        }
    }

    public function test_an_invoice_prints_the_persisted_totals_and_the_tenant_currency(): void
    {
        [, $invoice] = $this->bookingWithInvoice();
        $billing = $this->makeStaff($this->businessA, $this->adminA, 'billing');

        $this->actingAs($billing)->postJson("/api/v1/invoices/{$invoice['id']}/payments", [
            'amount' => 1000,
            'method' => 'cash',
            'reference' => 'RCPT-PRINT-1',
        ])->assertOk();

        $model = Invoice::findOrFail($invoice['id']);
        $document = $this->actingAs($billing)
            ->getJson("/api/v1/print/invoice/{$invoice['id']}")
            ->assertOk()
            ->json('data.document');

        $totals = collect($document['totals'])->keyBy('label');

        // Authoritative money: exactly what the billing service persisted.
        $this->assertSame('Rs. '.number_format((float) $model->subtotal, 2), $totals['Subtotal']['value']);
        $this->assertSame('Rs. '.number_format((float) $model->total, 2), $totals['Net total payable']['value']);
        $this->assertSame('Rs. '.number_format((float) $model->paid_total, 2), $totals['Amount paid']['value']);
        $this->assertSame('Rs. '.number_format((float) $model->balance_due, 2), $totals['Balance due']['value']);
        $this->assertSame('partially paid', strtolower($document['status']['label']));

        // The payment ledger is the document's ledger, not a second sum.
        $this->assertCount(1, $document['payments']);
        $this->assertSame('Rs. 1,000.00', $document['payments'][0]['amount']);

        // Tenant currency presentation, not a hardcoded symbol.
        $this->assertSame('Rs.', $document['branding']['currency']);
    }

    public function test_thermal_geometry_follows_the_tenant_and_the_workstation_calibration(): void
    {
        [, $invoice] = $this->bookingWithInvoice();

        // A printer that only exposes 58 mm of an 80 mm roll, with a longer feed.
        $this->actingAs($this->adminA)->putJson('/api/v1/settings/printing', [
            'device' => 'counter-1',
            'thermalSafeWidthMm' => 58,
            'thermalFeedMm' => 14,
        ])->assertOk();

        $document = $this->actingAs($this->adminA)
            ->getJson("/api/v1/print/receipt/{$invoice['id']}?device=counter-1")
            ->assertOk()
            ->json('data.document');

        $this->assertSame('thermal80', $document['paper']);
        // Numeric equality, not identity: JSON does not distinguish 58 from 58.0,
        // so asserting float identity over the wire would test the encoder.
        $this->assertEquals(58.0, $document['render']['safeWidthMm']);
        $this->assertEquals(14.0, $document['render']['feedMm']);
        $this->assertStringContainsString('58mm', $document['tokensCss']);

        // Another workstation keeps the tenant default: calibrations are
        // per-device, and a desk must never inherit a neighbour's dimensions.
        $other = $this->actingAs($this->adminA)
            ->getJson("/api/v1/print/receipt/{$invoice['id']}?device=counter-2")
            ->assertOk()
            ->json('data.document');

        $this->assertEquals(72.0, $other['render']['safeWidthMm']);
        $this->assertEquals(8.0, $other['render']['feedMm']);
    }

    public function test_print_settings_are_clamped_and_device_scoped(): void
    {
        $response = $this->actingAs($this->adminA)->putJson('/api/v1/settings/printing', [
            'thermalSafeWidthMm' => 900,
            'thermalFeedMm' => 500,
            'a4MarginPreset' => 'nonsense',
            'currencyStyle' => 'dogecoin',
            'dateFormat' => 'not-a-format',
            'invoiceFooterText' => '<script>alert(1)</script>Thank you.',
            'defaultPapers' => ['report' => 'thermal80', 'receipt' => 'a4'],
        ])->assertOk();

        $settings = $response->json('data.settings');

        $this->assertEquals(80.0, $settings['thermalSafeWidthMm'], 'A printable width cannot exceed the paper.');
        $this->assertEquals(30.0, $settings['thermalFeedMm']);
        $this->assertSame('standard', $settings['a4MarginPreset']);
        $this->assertSame('symbol', $settings['currencyStyle']);
        $this->assertSame('d M Y, h:i A', $settings['dateFormat']);

        // A clinical report is never printable on a receipt roll, whatever the
        // tenant asks for; a receipt may be printed on A4.
        $this->assertSame('a4', $settings['defaultPapers']['report']);
        $this->assertSame('a4', $settings['defaultPapers']['receipt']);

        // Tenant text is text: markup can never reach a document.
        $this->assertStringNotContainsString('<script', $settings['invoiceFooterText']);
        $this->assertStringContainsString('Thank you.', $settings['invoiceFooterText']);
    }

    public function test_calibrated_devices_do_not_overwrite_each_other(): void
    {
        $this->actingAs($this->adminA)->putJson('/api/v1/settings/printing', [
            'device' => 'counter-1',
            'calibration' => ['counter-1' => ['label' => 'Front desk', 'safeWidthMm' => 58, 'feedMm' => 12]],
        ])->assertOk();

        $this->actingAs($this->adminA)->putJson('/api/v1/settings/printing', [
            'device' => 'counter-2',
            'calibration' => ['counter-2' => ['label' => 'Pharmacy', 'safeWidthMm' => 64, 'feedMm' => 6]],
        ])->assertOk();

        $settings = $this->actingAs($this->adminA)->getJson('/api/v1/settings/printing')->assertOk()->json('data.settings');

        $this->assertEquals(58.0, $settings['calibration']['counter-1']['safeWidthMm']);
        $this->assertSame('Front desk', $settings['calibration']['counter-1']['label']);
        $this->assertEquals(64.0, $settings['calibration']['counter-2']['safeWidthMm']);
        $this->assertSame('Pharmacy', $settings['calibration']['counter-2']['label']);
    }

    public function test_a_signed_report_prints_its_clinical_text_verbatim_and_marked(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $study = $this->makeStudy(
            $this->businessA,
            $this->tenantService($this->businessA, 'CT-BRAIN-NC'),
            $this->makePatient($this->businessA, 'Verbatim Print Patient', 52),
        );

        $report = $this->actingAs($radiologist)->postJson("/api/v1/studies/{$study->id}/reports", [
            'clinicalHistory' => "Fall.\r\nSecond line of history.",
            'findings' => "1. 8 mm nodule.\r\n2. No effusion.",
            'impression' => 'Nodule — follow up.',
        ])->assertOk()->json('data.report');

        // An unsigned version prints MARKED as a draft: nobody may mistake a
        // draft impression for a signed one on paper.
        $draft = $this->actingAs($radiologist)->getJson("/api/v1/print/report/{$report['id']}")->assertOk()->json('data.document');
        $this->assertContains('DRAFT', array_column($draft['marks'], 'code'));
        $this->assertSame('draft', $draft['status']['code']);
        $this->assertFalse(RadiologyReport::findOrFail($report['id'])->isSigned());

        $signed = $this->actingAs($radiologist)->putJson("/api/v1/reports/{$report['id']}", [
            'clinicalHistory' => "Fall.\r\nSecond line of history.",
            'findings' => "1. 8 mm nodule.\r\n2. No effusion.",
            'impression' => 'Nodule — follow up.',
            'signNow' => true,
            'signAs' => 'final',
        ])->assertOk()->json('data.report');

        // Signing locks THIS version rather than creating a new one, so the
        // document that was a draft a moment ago is now the signed original.
        $this->assertTrue(RadiologyReport::findOrFail($report['id'])->isSigned());
        $this->assertSame($report['id'], $signed['id']);

        $document = $this->actingAs($radiologist)->getJson("/api/v1/print/report/{$signed['id']}")->assertOk()->json('data.document');

        $this->assertSame('signed', $document['status']['code']);
        $this->assertSame([], array_column($document['marks'], 'code'));

        $findings = collect($document['sections'])->firstWhere('label', 'Findings');
        // The wording, the numbering and the measurements print exactly as the
        // radiologist typed them. Only Windows line endings are normalised on the
        // way in — a stray CR renders as whitespace in the browser and would
        // indent the second line on paper.
        $this->assertSame("1. 8 mm nodule.\n2. No effusion.", $findings['body'], 'Clinical text must print exactly as written.');
        $this->assertStringNotContainsString("\r", $findings['body'], 'No carriage return may reach the rendered document.');
        $this->assertStringContainsString("Fall.\nSecond line of history.", collect($document['sections'])->firstWhere('label', 'Clinical indication')['body']);

        $html = view('print.document', ['document' => $document])->render();
        $this->assertStringContainsString('Electronically signed', $html);
        $this->assertStringContainsString($radiologist->name, $html);
    }

    public function test_printing_never_creates_a_business_record(): void
    {
        [$study, $invoice] = $this->bookingWithInvoice();
        $billing = $this->makeStaff($this->businessA, $this->adminA, 'billing');

        $before = [
            'invoices' => Invoice::where('business_id', $this->businessA->id)->count(),
            'payments' => \App\Models\InvoicePayment::where('business_id', $this->businessA->id)->count(),
            'studies' => \App\Models\Appointment::where('business_id', $this->businessA->id)->count(),
            'reports' => RadiologyReport::where('business_id', $this->businessA->id)->count(),
        ];

        // Print, reprint, download a PDF and file an audit event — twice.
        foreach ([1, 2] as $round) {
            $this->actingAs($billing)->getJson("/api/v1/print/invoice/{$invoice['id']}")->assertOk();
            $this->actingAs($billing)->getJson("/api/v1/print/invoice/{$invoice['id']}?reprint=1")->assertOk();
            $this->actingAs($billing)->get("/api/v1/invoices/{$invoice['id']}/pdf")->assertOk();
            $this->actingAs($billing)->postJson("/api/v1/print/invoice/{$invoice['id']}/events", [
                'event' => $round === 1 ? 'printed' : 'reprinted',
                'paper' => 'a4',
            ])->assertOk();
        }

        $after = [
            'invoices' => Invoice::where('business_id', $this->businessA->id)->count(),
            'payments' => \App\Models\InvoicePayment::where('business_id', $this->businessA->id)->count(),
            'studies' => \App\Models\Appointment::where('business_id', $this->businessA->id)->count(),
            'reports' => RadiologyReport::where('business_id', $this->businessA->id)->count(),
        ];

        $this->assertSame($before, $after, 'Printing must be a rendering action, never a financial or clinical one.');

        // The reprint is recorded, and the marking is what a reprint shows.
        $this->assertSame(2, \App\Models\AuditLog::where('action', 'document_printed')->count()
            + \App\Models\AuditLog::where('action', 'document_reprinted')->count());
        $this->assertSame(2, \App\Models\AuditLog::where('action', 'document_pdf_downloaded')->count());

        $this->assertContains(
            'REPRINT',
            array_column(
                $this->actingAs($billing)->getJson("/api/v1/print/invoice/{$invoice['id']}?reprint=1")->json('data.document.marks'),
                'code'
            )
        );
    }

    public function test_a_tenant_can_never_print_another_tenants_document(): void
    {
        [, $invoice] = $this->bookingWithInvoice();

        $foreign = $this->adminB;

        $this->actingAs($foreign)->getJson("/api/v1/print/invoice/{$invoice['id']}")->assertNotFound();
        $this->actingAs($foreign)->getJson("/api/v1/print/invoice/{$invoice['id']}/pdf")->assertNotFound();
        $this->actingAs($foreign)->getJson("/api/v1/print/receipt/{$invoice['id']}")->assertNotFound();

        // A miss is indistinguishable from a document that does not exist.
        $this->actingAs($foreign)->getJson('/api/v1/print/invoice/999999')->assertNotFound();
    }

    public function test_printing_is_gated_by_a_revocable_permission(): void
    {
        [, $invoice] = $this->bookingWithInvoice();

        // A technologist may label a study but has no invoice/report printing.
        $technician = $this->makeStaff($this->businessA, $this->adminA, 'technologist');

        $this->actingAs($technician)->getJson("/api/v1/print/invoice/{$invoice['id']}")->assertStatus(403);
        $this->actingAs($technician)->getJson("/api/v1/print/fee-schedule/current")->assertStatus(403);
        $this->actingAs($technician)->putJson('/api/v1/settings/printing', ['thermalFeedMm' => 12])->assertStatus(403);

        // Their own artifact still works.
        $this->actingAs($technician)->getJson("/api/v1/print/label/{$invoice['appointmentId']}")->assertOk();
    }
}
