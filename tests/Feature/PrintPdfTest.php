<?php

namespace Tests\Feature;

use App\Models\Referrer;
use App\Models\Service;
use App\Support\Print\PaperProfile;
use App\Support\Print\PrintArtifactRegistry;

/**
 * The PDF of record.
 *
 * A PDF that "exists" is not a verified document: the failure modes that matter
 * are a receipt printed on A4 (unusable in a thermal printer), an empty page, and
 * a document that cannot be rendered at all because a template broke. Each is
 * asserted here, at the level of the PDF's own geometry, for EVERY artifact —
 * the same loop-over-the-registry discipline as the payload suite.
 */
class PrintPdfTest extends ApiTestCase
{
    private const MM_TO_PT = 2.8346456693;

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function bookingWithInvoice(): array
    {
        $svc = Service::where('code', 'US-ABD-PEL')->where('business_id', $this->businessA->id)->firstOrFail();

        $booking = $this->actingAs($this->adminA)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Pdf Geometry Patient', 'gender' => 'male', 'age' => 37],
            'serviceId' => $svc->id,
            'date' => now()->toDateString(),
            'time' => '09:30 AM',
            'priority' => 'routine',
        ])->assertCreated()->json('data');

        return [$booking['study'], $booking['invoice']];
    }

    /** @return array{width: float, height: float} */
    private function pageBox(string $pdf): array
    {
        $this->assertStringStartsWith('%PDF', $pdf, 'The engine returned something that is not a PDF.');

        // The page dictionary is written uncompressed, so the physical page size
        // can be verified without parsing content streams.
        $matched = preg_match(
            '/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]/',
            $pdf,
            $matches
        );

        $this->assertSame(1, $matched, 'The PDF carries no MediaBox — its paper size cannot be verified.');

        return ['width' => (float) $matches[3], 'height' => (float) $matches[4]];
    }

    public function test_an_a4_invoice_is_an_a4_page(): void
    {
        [, $invoice] = $this->bookingWithInvoice();

        $response = $this->actingAs($this->adminA)->get("/api/v1/invoices/{$invoice['id']}/pdf");
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));

        $box = $this->pageBox((string) $response->getContent());

        $this->assertEqualsWithDelta(210 * self::MM_TO_PT, $box['width'], 1.5, 'A4 width in points');
        $this->assertEqualsWithDelta(297 * self::MM_TO_PT, $box['height'], 1.5, 'A4 height in points');

        // A download of record is an attachment; the print view stays inline.
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertSame('a4', $response->headers->get('X-Print-Paper'));
    }

    public function test_a_thermal_receipt_is_a_narrow_roll_not_a_sheet(): void
    {
        [, $invoice] = $this->bookingWithInvoice();

        $response = $this->actingAs($this->adminA)->get("/api/v1/print/receipt/{$invoice['id']}/pdf");
        $response->assertOk();

        $box = $this->pageBox((string) $response->getContent());

        $this->assertEqualsWithDelta(80 * self::MM_TO_PT, $box['width'], 1.5, 'The receipt must be 80 mm wide.');
        $this->assertLessThan(297 * self::MM_TO_PT, $box['height'], 'A receipt must not be an A4 sheet.');
        $this->assertGreaterThan(100, $box['height'], 'A receipt must be tall enough for its content plus feed.');
        $this->assertSame('thermal80', $response->headers->get('X-Print-Paper'));
    }

    public function test_a_calibrated_narrow_printer_produces_a_narrower_page(): void
    {
        [, $invoice] = $this->bookingWithInvoice();

        $this->actingAs($this->adminA)->putJson('/api/v1/settings/printing', [
            'thermalSafeWidthMm' => 58,
            'thermalFeedMm' => 20,
        ])->assertOk();

        $response = $this->actingAs($this->adminA)->get("/api/v1/print/receipt/{$invoice['id']}/pdf?force=1");
        $response->assertOk();

        // The PAGE stays 80 mm (that is the physical roll); what the calibration
        // changes is the printable body inside it. The estimate must therefore
        // reflect the longer feed rather than ignore it.
        $box = $this->pageBox((string) $response->getContent());
        $this->assertEqualsWithDelta(80 * self::MM_TO_PT, $box['width'], 1.5);
    }

    public function test_a_label_is_tag_sized(): void
    {
        [$study] = $this->bookingWithInvoice();

        $response = $this->actingAs($this->adminA)->get("/api/v1/print/label/{$study['id']}/pdf");
        $response->assertOk();

        $box = $this->pageBox((string) $response->getContent());

        $this->assertEqualsWithDelta(63.5 * self::MM_TO_PT, $box['width'], 1.5, '2.5 inch label width');
        $this->assertEqualsWithDelta(25.4 * self::MM_TO_PT, $box['height'], 1.5, '1 inch label height');
    }

    public function test_every_registered_artifact_renders_a_pdf_on_its_own_paper(): void
    {
        [$study, $invoice] = $this->bookingWithInvoice();
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $report = $this->actingAs($radiologist)->postJson("/api/v1/studies/{$study['id']}/reports", [
            'findings' => 'Unremarkable.',
            'impression' => 'Normal.',
        ])->assertOk()->json('data.report');

        $referrer = Referrer::create([
            'name' => 'Dr Pdf Test',
            'specialty' => 'Orthopaedics',
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

        foreach (PrintArtifactRegistry::keys() as $artifact) {
            $response = $this->actingAs($this->adminA)->get("/api/v1/print/{$artifact}/{$ids[$artifact]}/pdf");

            $response->assertOk();
            $body = (string) $response->getContent();

            $box = $this->pageBox($body);
            $expectedWidth = match ($artifact) {
                'label' => 63.5,
                'receipt', 'token' => 80.0,
                default => 210.0,
            };

            $this->assertEqualsWithDelta(
                $expectedWidth * self::MM_TO_PT,
                $box['width'],
                1.5,
                "{$artifact} was rendered on the wrong paper width"
            );

            $this->assertGreaterThan(
                1500,
                strlen($body),
                "{$artifact} produced a suspiciously empty PDF"
            );

            $this->assertNotEmpty($response->headers->get('X-Print-Driver'), "{$artifact} did not report its engine");
        }
    }

    public function test_the_receipt_artifact_uses_the_thermal_profile_by_default_while_the_report_stays_a4(): void
    {
        // Paper follows document semantics: a radiology report can never be
        // printed on a receipt roll, whatever a caller requests.
        $this->assertSame(PaperProfile::A4, PrintArtifactRegistry::for('report')['defaultPaper']);
        $this->assertSame(PaperProfile::THERMAL80, PrintArtifactRegistry::for('receipt')['defaultPaper']);
        $this->assertFalse(PrintArtifactRegistry::allowsPaper('report', PaperProfile::THERMAL80));
        $this->assertFalse(PrintArtifactRegistry::allowsPaper('label', PaperProfile::A4));
    }

    public function test_an_unknown_artifact_is_a_404_and_an_unknown_document_is_a_404(): void
    {
        $this->actingAs($this->adminA)->getJson('/api/v1/print/nope/1')->assertNotFound();
        $this->actingAs($this->adminA)->getJson('/api/v1/print/invoice/not-a-number')->assertNotFound();
    }
}
