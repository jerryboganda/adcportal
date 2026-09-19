<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\TenantIntegration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Dictation through the clinic's OWN speech-to-text service.
 *
 * Browser speech recognition may send clinical audio to a vendor's cloud
 * service; this path exists so a clinic can keep it inside its own network. The
 * tests pin the properties that make that claim true: audio goes only to the
 * configured engine of the ACTING tenant, nothing is stored, a failure is
 * reported honestly, and an unconfigured clinic is told so instead of silently
 * dictating through something half-set-up.
 */
class ReportDictationTest extends ApiTestCase
{
    private function dictationIntegration(
        $business,
        string $status = 'active',
        array $config = ['baseUrl' => 'https://stt.alpha.test/v1/transcribe'],
        array $secrets = [],
    ): TenantIntegration {
        return TenantIntegration::create([
            'business_id' => $business->id,
            'type' => 'dictation',
            'name' => 'Clinic STT engine',
            'config' => $config,
            'secrets' => $secrets,
            'status' => $status,
        ]);
    }

    /**
     * A recording with ACTUAL bytes in it.
     *
     * `UploadedFile::fake()->create()` writes nothing at all — it only reports a
     * size — so the service would correctly reject it as an empty recording.
     * The service reads the upload's real content, so the fixture must have
     * some.
     */
    private function audio(string $content = 'webm-audio-payload'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('dictation.webm', $content);
    }

    /** A recording that only REPORTS an oversized length (the content is never read: validation refuses it first). */
    private function oversizedAudio(int $megabytes = 20): UploadedFile
    {
        return UploadedFile::fake()->create('dictation.webm', $megabytes * 1024, 'audio/webm');
    }

    public function test_a_clinic_without_an_engine_is_told_so_plainly(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $this->actingAs($radiologist)->getJson('/api/v1/reporting/dictation')
            ->assertOk()
            ->assertJsonPath('data.serverProvider.available', false);

        // No engine → the editor keeps working; it is not a permission error.
        Http::fake();
        $this->actingAs($radiologist)
            ->postJson('/api/v1/reporting/dictation/transcribe', ['audio' => $this->audio()])
            ->assertStatus(409)
            ->assertJsonPath('error', 'dictation.unconfigured');

        Http::assertNothingSent();
    }

    public function test_an_incomplete_integration_is_not_used(): void
    {
        // Present but never health-checked to active: dictating through a
        // half-configured endpoint would be worse than saying "unavailable".
        $this->dictationIntegration($this->businessA, status: 'unconfigured');
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $this->actingAs($radiologist)->getJson('/api/v1/reporting/dictation')
            ->assertOk()
            ->assertJsonPath('data.serverProvider.available', false);

        Http::fake();
        $this->actingAs($radiologist)
            ->postJson('/api/v1/reporting/dictation/transcribe', ['audio' => $this->audio()])
            ->assertStatus(409);

        Http::assertNothingSent();
    }

    public function test_audio_is_transcribed_by_the_clinics_own_engine(): void
    {
        $this->dictationIntegration($this->businessA, secrets: ['apiKey' => 'stt-secret']);
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        Http::fake([
            'stt.alpha.test/*' => Http::response(['text' => 'No acute intracranial haemorrhage.'], 200),
        ]);

        $this->actingAs($radiologist)->getJson('/api/v1/reporting/dictation')
            ->assertOk()
            ->assertJsonPath('data.serverProvider.available', true)
            ->assertJsonPath('data.serverProvider.label', 'Clinic STT engine');

        $this->actingAs($radiologist)
            ->postJson('/api/v1/reporting/dictation/transcribe', [
                'audio' => $this->audio(),
                'language' => 'en-GB',
            ])
            ->assertOk()
            ->assertJsonPath('data.text', 'No acute intracranial haemorrhage.');

        Http::assertSent(function ($request) {
            $body = $request->body();

            return str_contains($request->url(), 'stt.alpha.test/v1/transcribe')
                && $request->hasHeader('Authorization', 'Bearer stt-secret')
                // The audio is posted as a multipart file, never a JSON blob.
                && str_contains($body, 'name="file"')
                && str_contains($body, 'name="language"');
        });
    }

    public function test_a_google_style_response_is_understood(): void
    {
        // Self-hosted engines do not agree on a response shape, so the common
        // ones are all recognised rather than only the first one written.
        $this->dictationIntegration($this->businessA);
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        Http::fake(['*' => Http::response([
            'results' => [['alternatives' => [['transcript' => 'Ventricles are normal.']]]],
        ], 200)]);

        $this->actingAs($radiologist)
            ->postJson('/api/v1/reporting/dictation/transcribe', ['audio' => $this->audio()])
            ->assertOk()
            ->assertJsonPath('data.text', 'Ventricles are normal.');
    }

    public function test_an_unrecognised_response_is_never_pasted_into_a_report(): void
    {
        // A body the product does not understand yields NO text rather than
        // some slice of it: a medical record must never receive payload junk.
        $this->dictationIntegration($this->businessA);
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        Http::fake(['*' => Http::response(['unexpected' => ['shape' => true]], 200)]);

        $this->actingAs($radiologist)
            ->postJson('/api/v1/reporting/dictation/transcribe', ['audio' => $this->audio()])
            ->assertOk()
            ->assertJsonPath('data.text', '');
    }

    public function test_an_engine_failure_is_reported_and_never_recorded_as_text(): void
    {
        $this->dictationIntegration($this->businessA);
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        Http::fake(['*' => Http::response(['error' => 'engine exploded'], 500)]);

        $response = $this->actingAs($radiologist)
            ->postJson('/api/v1/reporting/dictation/transcribe', ['audio' => $this->audio()])
            ->assertStatus(502);

        // The engine's raw body is never echoed back into a clinical UI.
        $this->assertStringNotContainsString('engine exploded', $response->getContent());
        $this->assertSame('dictation.failed', $response->json('error'));
    }

    public function test_another_clinics_engine_is_never_used(): void
    {
        // Beta has an engine; Alpha does not. Alpha's radiologist must be told
        // dictation is unavailable — and Alpha's audio must not leave for Beta.
        $this->dictationIntegration($this->businessB, config: ['baseUrl' => 'https://stt.beta.test/transcribe']);
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        Http::fake();

        $this->actingAs($radiologist)->getJson('/api/v1/reporting/dictation')
            ->assertOk()
            ->assertJsonPath('data.serverProvider.available', false);

        $this->actingAs($radiologist)
            ->postJson('/api/v1/reporting/dictation/transcribe', ['audio' => $this->audio()])
            ->assertStatus(409);

        Http::assertNothingSent();
    }

    public function test_dictation_records_an_audit_trail_without_the_content(): void
    {
        $this->dictationIntegration($this->businessA);
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $study = $this->makeStudy(
            $this->businessA,
            $this->tenantService($this->businessA, 'US-ABD-PEL'),
            $this->makePatient($this->businessA, 'Dilated Patient', 44),
        );

        Http::fake(['*' => Http::response(['text' => 'Spleen is unremarkable.'], 200)]);

        $this->actingAs($radiologist)
            ->postJson('/api/v1/reporting/dictation/transcribe', [
                'audio' => $this->audio(),
                'appointmentId' => (int) $study->id,
            ])
            ->assertOk();

        $entry = AuditLog::where('action', 'report_dictation')->firstOrFail();

        // Audited: that dictation happened, for WHICH study, through which
        // engine, and how long it took.
        $this->assertSame($this->businessA->id, $entry->business_id);
        $this->assertSame('Appointment', $entry->subject_type);
        $this->assertSame($study->id, $entry->subject_id);
        $this->assertSame($study->id, $entry->changes['appointmentId']);
        $this->assertArrayHasKey('latencyMs', $entry->changes);
        $this->assertSame(mb_strlen('Spleen is unremarkable.'), $entry->changes['characters']);

        // Not audited: the transcript, or anything else clinical.
        $this->assertStringNotContainsString('Spleen', json_encode($entry->changes));
    }

    public function test_a_dictation_event_cannot_be_attached_to_another_clinics_study(): void
    {
        $this->dictationIntegration($this->businessA);
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $foreign = $this->makeStudy(
            $this->businessB,
            $this->tenantService($this->businessB, 'CT-BRAIN-NC'),
            $this->makePatient($this->businessB, 'Beta Patient', 30),
        );

        Http::fake(['*' => Http::response(['text' => 'Normal study.'], 200)]);

        $this->actingAs($radiologist)
            ->postJson('/api/v1/reporting/dictation/transcribe', [
                'audio' => $this->audio(),
                'appointmentId' => (int) $foreign->id,
            ])
            ->assertOk();

        $entry = AuditLog::where('action', 'report_dictation')->firstOrFail();

        // The foreign id resolves to nothing, so the event is recorded against
        // the integration instead of claiming someone else's study.
        $this->assertSame('TenantIntegration', $entry->subject_type);
        $this->assertNull($entry->changes['appointmentId']);
    }

    public function test_an_oversized_recording_is_refused_before_it_is_sent(): void
    {
        $this->dictationIntegration($this->businessA);
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        Http::fake();

        // 20 MB — past the ceiling for a single pass.
        $this->actingAs($radiologist)
            ->postJson('/api/v1/reporting/dictation/transcribe', ['audio' => $this->oversizedAudio()])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_dictation_requires_report_authoring_rights(): void
    {
        $this->dictationIntegration($this->businessA);
        $receptionist = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        Http::fake();

        // Reading the capability is part of the reporting module…
        $this->actingAs($receptionist)->getJson('/api/v1/reporting/dictation')->assertForbidden();

        // …and posting audio is only for users who author reports.
        $this->actingAs($receptionist)
            ->postJson('/api/v1/reporting/dictation/transcribe', ['audio' => $this->audio()])
            ->assertForbidden();

        Http::assertNothingSent();
    }
}
