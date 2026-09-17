<?php

namespace App\Services\Delivery;

use App\Models\TenantIntegration;

/**
 * HL7 v2 over MLLP (Minimal Lower Layer Protocol). Frames the message with
 * the standard 0x0B … 0x1C CR bytes, writes it to the configured host:port,
 * and WAITS FOR THE ACK — a delivery is only `sent` when an AA|CA ACK comes
 * back (AE/CE application-reject and AR/CR are failures, per HL7 AR rules).
 */
class Hl7MllpSender implements ChannelSender
{
    public const MLLP_START = "\x0b"; // vertical tab
    public const MLLP_END = "\x1c\r"; // file separator + CR

    public function send(TenantIntegration $integration, string $event, array $payload): DeliveryResult
    {
        $config = is_array($integration->config) ? $integration->config : [];

        $host = (string) ($config['host'] ?? '');
        $port = (int) ($config['port'] ?? 2575);
        $sendingApp = (string) ($config['sendingApp'] ?? 'POLYTRONX_RIS');
        $sendingFacility = (string) ($config['sendingFacility'] ?? $integration->business?->tenant_code ?? 'RIS');

        $to = (string) ($payload['to'] ?? '');
        $message = (string) ($payload['message'] ?? '');

        if ($host === '' || $port <= 0) {
            return DeliveryResult::skipped('HL7 endpoint host/port are not configured.');
        }

        // An explicit raw message always wins (complete control for the sender);
        // otherwise a minimal, valid ADT-style message is built from the payload.
        $body = $message !== ''
            ? $message
            : $this->buildAdtMessage($event, $payload, $sendingApp, $sendingFacility);

        $framed = self::MLLP_START.$body.self::MLLP_END;

        $start = microtime(true);

        $socket = @fsockopen($host, $port, $errNo, $errStr, 3);
        if (! is_resource($socket)) {
            return DeliveryResult::failed("MLLP connect failed to {$host}:{$port} — ".trim((string) $errStr).'.', (int) round((microtime(true) - $start) * 1000));
        }

        stream_set_timeout($socket, 5);
        $written = fwrite($socket, $framed);

        if ($written === false || $written < strlen($framed)) {
            fclose($socket);

            return DeliveryResult::failed("MLLP write failed to {$host}:{$port} (wrote ".var_export($written, true).' of '.strlen($framed).' bytes).', (int) round((microtime(true) - $start) * 1000));
        }

        // Read until MLLP end bytes (or timeout) to collect the ACK.
        $ack = '';
        while (! str_contains($ack, self::MLLP_END)) {
            $chunk = fread($socket, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $ack .= $chunk;
        }
        fclose($socket);

        $latency = (int) round((microtime(true) - $start) * 1000);

        if ($ack === '') {
            return DeliveryResult::failed("No MLLP ACK received from {$host}:{$port} within the timeout.", $latency);
        }

        $ackCode = $this->ackCode($ack);

        // AA/CA = accept; AE/CE = application error; AR/CR = rejected.
        if ($ackCode !== null && in_array($ackCode[1], ['A', 'C'], true)) {
            return DeliveryResult::sent("HL7 ACK {$ackCode} received from {$host}:{$port}.", $latency, [
                'ack_code' => $ackCode,
            ]);
        }

        return DeliveryResult::failed("HL7 endpoint responded with ".($ackCode ?? 'an unreadable')." ACK for {$host}:{$port}.", $latency, [
            'ack_code' => $ackCode,
        ]);
    }

    /** Minimal but valid ADT^A04-style message (segment terminator \r). */
    private function buildAdtMessage(string $event, array $payload, string $app, string $facility): string
    {
        $now = now()->format('YmdHis');
        $patientId = (string) ($payload['patientId'] ?? $payload['studyId'] ?? 'UNKNOWN');
        $patientName = (string) ($payload['patientName'] ?? 'Unknown^Patient');
        $study = (string) ($payload['study'] ?? $event);

        $segments = [
            "MSH|^~\\&|{$app}|{$facility}|HIS|HIS_FACILITY|{$now}||ADT^A04|".strtoupper(substr(md5($now.$patientId), 0, 10))."|P|2.5",
            "EVN|A04|{$now}",
            "PID|1||{$patientId}||{$patientName}",
            "OBR|1||{$patientId}|{$study}|{$study}|{$now}",
        ];

        return implode("\r", $segments);
    }

    /** Extract the ACK code (e.g. `AA`) from MSA-1 of the raw ACK. */
    private function ackCode(string $rawAck): ?string
    {
        foreach (explode("\r", str_replace(["\n", self::MLLP_START, self::MLLP_END], "\r", $rawAck)) as $segment) {
            if (str_starts_with(trim($segment), 'MSA|')) {
                $fields = explode('|', trim($segment));

                return isset($fields[1]) && $fields[1] !== '' ? strtoupper(substr($fields[1], 0, 2)) : null;
            }
        }

        return null;
    }
}
