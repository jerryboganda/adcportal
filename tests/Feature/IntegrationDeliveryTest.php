<?php

namespace Tests\Feature;

use App\Enums\StudyState;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\IntegrationDelivery;
use App\Models\RadiologyReport;
use App\Models\TenantIntegration;
use App\Services\Delivery\DeliveryDispatcher;
use App\Services\Delivery\DeliveryResult;
use App\Services\Delivery\FhirSender;
use App\Services\Delivery\Hl7MllpSender;
use App\Services\Delivery\WebhookSender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * End-to-end delivery engine tests. Every channel is exercised through the
 * REAL sender code paths — HTTP fakes assert request shape (auth, signing,
 * endpoints, payloads), a real in-process socket asserts MLLP framing + ACK
 * handling, and the delivery log is asserted for truthful outcome recording.
 */
class IntegrationDeliveryTest extends ApiTestCase
{
    private Business $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->businessA;
        $this->actingAs($this->adminA);
    }

    private function makeIntegration(string $type, array $config = [], array $secrets = []): TenantIntegration
    {
        return TenantIntegration::create([
            'business_id' => $this->tenant->id,
            'type' => $type,
            'name' => ucfirst($type).' test '.uniqid(),
            'config' => $config,
            'secrets' => $secrets,
            'status' => 'active',
        ]);
    }

    /** A signed study ready for dispatch. */
    private function makeSignedStudy(): Appointment
    {
        $svc = \App\Models\Service::forClinic($this->tenant->id)->first();

        $appointment = Appointment::create([
            'customer_id' => $this->adminA->id,
            'name' => 'Dispatch Test Patient',
            'service_id' => $svc->id,
            'date' => now()->toDateString(),
            'time' => '10:00:00',
            'priority' => 'routine',
            'workflow_state' => StudyState::Reported->value,
            'screening_required' => false,
            'screening_cleared' => true,
            'business_id' => $this->tenant->id,
            // The real author id, not a literal 1. User ids come from a shared
            // sequence across every tenant fixture, so "user 1" is only this
            // tenant's admin by luck of insertion order. PostgreSQL enforces
            // appointments_created_by_foreign and SQLite does not, so the
            // hardcoded id was a latent violation that only the production
            // engine could see.
            'created_by' => $this->adminA->id,
        ]);

        RadiologyReport::create([
            'appointment_id' => $appointment->id,
            'version' => 1,
            'type' => 'final',
            'findings' => 'Test findings.',
            'impression' => 'Normal study.',
            'authored_by' => $this->adminA->id,
            'signed_by' => $this->adminA->id,
            'signed_at' => now(),
            'locked_at' => now(),
            'business_id' => $this->tenant->id,
        ]);

        return $appointment->refresh();
    }

    // ==================== webhook ====================

    public function test_webhook_sends_hmac_signed_payload_and_records_delivery(): void
    {
        Http::fake(['webhook.example/receive' => Http::response(['ok' => true], 200)]);

        $integration = $this->makeIntegration('webhook', ['url' => 'https://webhook.example/receive'], ['signingSecret' => 'topsecret']);

        $result = app(DeliveryDispatcher::class)->sendNow($integration, 'study.booked', [
            'token' => 'TOK-1', 'patientName' => 'Test^Patient', 'study' => 'MRI Brain',
        ]);

        $this->assertSame(DeliveryResult::SENT, $result->status, $result->detail ?? '');

        Http::assertSent(function ($request) {
            $sig = $request->header(WebhookSender::HEADER_SIGNATURE)[0] ?? '';
            $ts = $request->header(WebhookSender::HEADER_TIMESTAMP)[0] ?? '';
            $this->assertNotSame('', $sig);
            $this->assertNotSame('', $ts);

            // Receiver-side verification: HMAC over timestamp.body with the secret.
            $expected = hash_hmac('sha256', $ts.'.'.$request->body(), 'topsecret');
            $this->assertSame($expected, $sig);
            $this->assertSame('study.booked', $request->header(WebhookSender::HEADER_EVENT)[0]);

            $body = json_decode($request->body(), true);
            $this->assertSame('study.booked', $body['event']);
            $this->assertSame('TOK-1', $body['data']['token']);

            return true;
        });

        $this->assertDeliveryLogged($integration, 'sent');
    }

    public function test_webhook_5xx_is_recorded_as_failed(): void
    {
        Http::fake(['webhook.example/fail' => Http::response(['nope' => true], 500)]);

        $integration = $this->makeIntegration('webhook', ['url' => 'https://webhook.example/fail'], ['signingSecret' => 's']);

        $result = app(DeliveryDispatcher::class)->sendNow($integration, 'test', []);

        $this->assertSame(DeliveryResult::FAILED, $result->status);
        $this->assertDeliveryLogged($integration, 'failed');
    }

    // ==================== whatsapp ====================

    public function test_whatsapp_sends_graph_api_message_with_token(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/1234567890/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.TEST123']],
            ], 200),
        ]);

        $integration = $this->makeIntegration(
            'whatsapp',
            ['phoneNumberId' => '1234567890'],
            ['accessToken' => 'eaag-token'],
        );

        $result = app(DeliveryDispatcher::class)->sendNow($integration, 'report_dispatch', [
            'to' => '+923001234567',
            'message' => 'Your report is ready',
        ]);

        $this->assertSame(DeliveryResult::SENT, $result->status, $result->detail ?? '');
        $this->assertSame('wamid.TEST123', $result->meta['wamid'] ?? null);

        Http::assertSent(function ($request) {
            $this->assertSame('Bearer eaag-token', $request->header('Authorization')[0]);

            $body = $request->data();
            $this->assertSame('whatsapp', $body['messaging_product']);
            $this->assertSame('+923001234567', $body['to']);
            $this->assertSame('Your report is ready', $body['text']['body']);

            return true;
        });

        $this->assertDeliveryLogged($integration, 'sent');
    }

    public function test_whatsapp_error_response_is_failed_with_provider_message(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/*/messages' => Http::response([
                'error' => ['message' => 'Invalid OAuth access token', 'type' => 'OAuthException', 'code' => 190],
            ], 401),
        ]);

        $integration = $this->makeIntegration('whatsapp', ['phoneNumberId' => '42'], ['accessToken' => 'bad']);

        $result = app(DeliveryDispatcher::class)->sendNow($integration, 'report_dispatch', [
            'to' => '+923000000000', 'message' => 'x',
        ]);

        $this->assertSame(DeliveryResult::FAILED, $result->status);
        $this->assertStringContainsString('Invalid OAuth access token', $result->detail ?? '');
    }

    // ==================== sms ====================

    public function test_sms_json_gateway_sends_bearer_authenticated_payload(): void
    {
        Http::fake(['sms.example/send' => Http::response(['messageId' => 'SM-77'], 200)]);

        $integration = $this->makeIntegration(
            'sms',
            ['provider' => 'json', 'endpoint' => 'https://sms.example/send', 'from' => 'RIS-ALERT'],
            ['apiKey' => 'gw-key'],
        );

        $result = app(DeliveryDispatcher::class)->sendNow($integration, 'report_dispatch', [
            'to' => '+923009988776', 'message' => 'Report ready',
        ]);

        $this->assertSame(DeliveryResult::SENT, $result->status, $result->detail ?? '');
        $this->assertSame('SM-77', $result->meta['message_id'] ?? null);

        Http::assertSent(function ($request) {
            $this->assertSame('Bearer gw-key', $request->header('Authorization')[0]);
            $body = $request->data();
            $this->assertSame('+923009988776', $body['to']);
            $this->assertSame('RIS-ALERT', $body['from']);

            return true;
        });

        $this->assertDeliveryLogged($integration, 'sent');
    }

    public function test_sms_twilio_gateway_uses_basic_auth_form_post(): void
    {
        Http::fake(['https://api.twilio.com/2010-04-01/Accounts/ACcc/Messages.json' => Http::response(['sid' => 'SMtw'], 201)]);

        $integration = $this->makeIntegration(
            'sms',
            ['provider' => 'twilio', 'endpoint' => 'https://api.twilio.com/2010-04-01/Accounts/ACcc/Messages.json', 'from' => '+15550001'],
            ['apiKey' => 'ACcc:authtoken'],
        );

        $result = app(DeliveryDispatcher::class)->sendNow($integration, 'report_dispatch', [
            'to' => '+15550002', 'message' => 'hello',
        ]);

        $this->assertSame(DeliveryResult::SENT, $result->status, $result->detail ?? '');

        Http::assertSent(function ($request) {
            $expected = 'Basic '.base64_encode('ACcc:authtoken');
            $this->assertSame($expected, $request->header('Authorization')[0]);
            $this->assertSame('+15550002', $request['To']);
            $this->assertSame('hello', $request['Body']);

            return true;
        });
    }

    // ==================== email ====================

    public function test_email_send_never_fakes_success_and_logs_truthfully(): void
    {
        $integration = $this->makeIntegration(
            'email',
            ['host' => 'smtp.invalid-host.test', 'port' => '2525', 'fromAddress' => 'reports@tenant.example'],
            ['username' => 'smtp-user', 'password' => 'smtp-pass'],
        );

        $result = app(DeliveryDispatcher::class)->sendNow($integration, 'report_dispatch', [
            'to' => 'doctor@example.com', 'subject' => 'Report', 'message' => 'body',
        ]);

        // An unresolvable SMTP host must FAIL — never silently claim success.
        $this->assertSame(DeliveryResult::FAILED, $result->status);
        $this->assertDeliveryLogged($integration, 'failed');
    }

    // ==================== HL7 / MLLP ====================

    /**
     * REAL end-to-end MLLP exchange: the sender talks over TCP to an
     * in-process sink that asserts the received frame is properly
     * MLLP-framed and answers an AA ACK. Cross-platform (no pcntl): the
     * sink runs as a background child PHP process on the same port.
     */
    public function test_hl7_sends_framed_message_and_receives_aa_ack(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertIsResource($server);
        $name = stream_socket_get_name($server, false);
        $port = (int) substr($name, strrpos($name, ':') + 1);
        fclose($server);

        // Standalone sink process: accepts connections, asserts framing of
        // what it receives, replies with an AA ACK, records received bytes.
        $sinkScript = implode("\n", [
            '<?php',
            '$srv = stream_socket_server("tcp://127.0.0.1:'.$port.'", $e, $s);',
            'if (!$srv) { fwrite(STDERR, "bind failed"); exit(1); }',
            '$conn = stream_socket_accept($srv, 10);',
            'if (!$conn) { exit(1); }',
            '$data = "";',
            'while (!str_contains($data, "\x1c\r")) {',
            '    $chunk = fread($conn, 8192);',
            '    if ($chunk === false || $chunk === "") break;',
            '    $data .= $chunk;',
            '}',
            'file_put_contents(getenv("SINK_OUT"), $data);',
            '$ack = "\x0bMSH|^~\\&|HIS|F|RIS|F|20260917120000||ACK|1|P|2.5\rMSA|AA|MSG1\r\x1c\r";',
            'fwrite($conn, $ack);',
            'fclose($conn);',
            'exit(0);',
        ]);
        $sinkOut = tempnam(sys_get_temp_dir(), 'mllp');
        $sinkFile = tempnam(sys_get_temp_dir(), 'mllp').'.php';
        file_put_contents($sinkFile, str_replace('getenv("SINK_OUT")', '$argv[1]', $sinkScript));

        $php = PHP_BINARY;
        $cmd = escapeshellarg($php).' '.escapeshellarg($sinkFile).' '.escapeshellarg($sinkOut);
        $process = proc_open($cmd, [], $pipes);
        $this->assertIsResource($process);

        // Give the sink a moment to bind.
        usleep(300000);

        $integration = $this->makeIntegration('hl7', ['host' => '127.0.0.1', 'port' => (string) $port]);

        $result = app(DeliveryDispatcher::class)->sendNow($integration, 'study.booked', [
            'patientId' => 'P-001', 'patientName' => 'Test^Patient', 'study' => 'MRI Brain',
        ]);

        $this->assertSame(DeliveryResult::SENT, $result->status, $result->detail ?? '');
        $this->assertSame('AA', $result->meta['ack_code'] ?? null);

        $received = (string) file_get_contents($sinkOut);
        $this->assertStringStartsWith(Hl7MllpSender::MLLP_START, $received, 'Frame must start with MLLP VT byte');
        $this->assertStringEndsWith(Hl7MllpSender::MLLP_END, $received, 'Frame must end with FS CR bytes');
        $this->assertStringContainsString('MSH|^~\\&|', $received);
        $this->assertStringContainsString('PID|1||P-001||Test^Patient', $received);

        proc_terminate($process);
        proc_close($process);
        @unlink($sinkOut);
        @unlink($sinkFile);
        $this->assertDeliveryLogged($integration, 'sent');
    }

    public function test_hl7_ack_parser_accepts_and_rejects_correctly(): void
    {
        $sender = new Hl7MllpSender();
        $method = new \ReflectionMethod($sender, 'ackCode');
        $method->setAccessible(true);

        $this->assertSame('AA', $method->invoke($sender, Hl7MllpSender::MLLP_START."MSH|...\rMSA|AA|x\r".Hl7MllpSender::MLLP_END));
        $this->assertSame('CA', $method->invoke($sender, Hl7MllpSender::MLLP_START."MSH|...\rMSA|CA|x\r".Hl7MllpSender::MLLP_END));
        $this->assertSame('AR', $method->invoke($sender, "MSH|...\r\nMSA|AR|y\r\n"));
        $this->assertSame('AE', $method->invoke($sender, "MSA|AE|z"));
        $this->assertNull($method->invoke($sender, 'garbage-no-msa'));
    }

    // ==================== fhir ====================

    public function test_fhir_posts_transaction_bundle(): void
    {
        Http::fake(['fhir.example/*' => Http::response([
            'resourceType' => 'Bundle', 'type' => 'transaction-response', 'entry' => [],
        ], 200)]);

        $integration = $this->makeIntegration(
            'fhir',
            ['baseUrl' => 'https://fhir.example/base'],
            ['clientId' => 'client-id', 'clientSecret' => 'client-secret'],
        );

        $result = app(DeliveryDispatcher::class)->sendNow($integration, 'study.booked', [
            'token' => 'TOK-9', 'study' => 'CT Chest',
        ]);

        $this->assertSame(DeliveryResult::SENT, $result->status, $result->detail ?? '');

        Http::assertSent(function ($request) {
            $this->assertSame('Basic '.base64_encode('client-id:client-secret'), $request->header('Authorization')[0]);

            $body = $request->data();
            $this->assertSame('Bundle', $body['resourceType']);
            $this->assertSame('transaction', $body['type']);
            $this->assertCount(2, $body['entry']);
            $this->assertSame('Task', $body['entry'][0]['resource']['resourceType']);

            return true;
        });

        $this->assertDeliveryLogged($integration, 'sent');
    }

    public function test_fhir_probe_metadata_requires_capability_statement(): void
    {
        Http::fake([
            'fhir-ok.example/metadata' => Http::response(['resourceType' => 'CapabilityStatement'], 200),
            'fhir-bad.example/metadata' => Http::response(['not' => 'fhir'], 200),
        ]);

        $ok = FhirSender::probeMetadata('https://fhir-ok.example');
        $bad = FhirSender::probeMetadata('https://fhir-bad.example');

        $this->assertSame('active', $ok['status']);
        $this->assertSame('error', $bad['status']);
    }

    // ==================== dispatcher: fan-out + skips ====================

    public function test_fanout_only_targets_active_integrations_of_requested_types(): void
    {
        Http::fake(['hook.example/h' => Http::response(['ok' => true], 200)]);

        $hook = $this->makeIntegration('webhook', ['url' => 'https://hook.example/h'], ['signingSecret' => 's']);
        $inactive = $this->makeIntegration('webhook', ['url' => 'https://hook.example/inactive'], ['signingSecret' => 's']);
        $inactive->update(['status' => 'unconfigured']);
        $hl7 = $this->makeIntegration('hl7', ['host' => '127.0.0.1', 'port' => '1']);

        app(DeliveryDispatcher::class)->fanOut($this->tenant->id, ['webhook', 'hl7'], 'study.booked', []);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'hook.example/h'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'hook.example/inactive'));

        // Only the healthy webhook recorded a delivery; HL7 to port 1 fails locally.
        $this->assertTrue(IntegrationDelivery::where('tenant_integration_id', $hook->id)->where('status', 'sent')->exists());
        $this->assertTrue(IntegrationDelivery::where('tenant_integration_id', $hl7->id)->where('status', 'failed')->exists());
        $this->assertFalse(IntegrationDelivery::where('tenant_integration_id', $inactive->id)->exists());
    }

    public function test_dicom_type_has_no_message_delivery_and_is_skipped_truthfully(): void
    {
        $dicom = $this->makeIntegration('dicom', ['host' => 'pacs.example', 'port' => '104', 'aeTitle' => 'PACS']);

        $result = app(DeliveryDispatcher::class)->sendNow($dicom, 'test', []);

        $this->assertSame(DeliveryResult::SKIPPED, $result->status);
        $this->assertDeliveryLogged($dicom, 'skipped');
    }

    public function test_delivery_log_never_contains_secret_material(): void
    {
        Http::fake(['hook.example/x' => Http::response(['ok' => true], 200)]);

        $integration = $this->makeIntegration('webhook', ['url' => 'https://hook.example/x'], ['signingSecret' => 'SUPERSECRETVALUE']);

        app(DeliveryDispatcher::class)->sendNow($integration, 'test', []);

        $row = IntegrationDelivery::where('tenant_integration_id', $integration->id)->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertStringNotContainsString('SUPERSECRETVALUE', json_encode($row->toArray()));
    }

    // ==================== test-delivery endpoint ====================

    public function test_platform_test_delivery_endpoint_runs_real_send(): void
    {
        Http::fake(['hook.example/t' => Http::response(['ok' => true], 200)]);

        $integration = $this->makeIntegration('webhook', ['url' => 'https://hook.example/t'], ['signingSecret' => 's']);

        $super = \App\Models\User::create([
            'name' => 'Platform Test Admin',
            'email' => 'platform-admin-'.uniqid().'@test.local',
            'password' => 'Platform#Test2026',
            'type' => 'super_admin',
            'active_status' => 1,
            'business_id' => 0,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($super)->confirmStepUp()->postJson("/api/v1/platform/tenants/{$this->tenant->id}/integrations/{$integration->id}/test");

        $response->assertOk();
        $this->assertSame('sent', $response->json('data.test.status'), json_encode($response->json('data.test')));
        $this->assertDeliveryLogged($integration, 'sent');
    }

    // ==================== dispatch flow (SettingsController) ====================

    public function test_whatsapp_dispatch_uses_gateway_and_records_delivered(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/*/messages' => Http::response(['messages' => [['id' => 'wamid.X']]], 200),
        ]);

        $this->makeIntegration('whatsapp', ['phoneNumberId' => '999'], ['accessToken' => 'tok']);

        $appointment = $this->makeSignedStudy();

        $response = $this->postJson('/api/v1/dispatches', [
            'appointmentId' => $appointment->id,
            'channel' => 'whatsapp',
            'recipientContact' => '+923001112223',
        ]);

        $response->assertCreated();
        $dispatch = \App\Models\DoctorDispatchLog::where('business_id', $this->tenant->id)->latest('id')->first();
        $this->assertSame('delivered', $dispatch->status, $dispatch->failure_detail ?? '');
    }

    public function test_whatsapp_dispatch_without_integration_records_failure_detail(): void
    {
        $appointment = $this->makeSignedStudy();

        $response = $this->postJson('/api/v1/dispatches', [
            'appointmentId' => $appointment->id,
            'channel' => 'whatsapp',
            'recipientContact' => '+923001112223',
        ]);

        $response->assertCreated();
        $dispatch = \App\Models\DoctorDispatchLog::where('business_id', $this->tenant->id)->latest('id')->first();
        $this->assertSame('failed', $dispatch->status);
        $this->assertStringContainsString('No active whatsapp integration', $dispatch->failure_detail ?? '');
    }

    // ==================== helper ====================

    private function assertDeliveryLogged(TenantIntegration $integration, string $status): void
    {
        $this->assertTrue(
            IntegrationDelivery::where('tenant_integration_id', $integration->id)->where('status', $status)->exists(),
            "Expected a {$status} delivery logged for integration {$integration->id}"
        );
    }
}
