<?php

namespace App\Services\Delivery;

use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Mail;

/**
 * Outbound email through the tenant's OWN SMTP credentials. Laravel's
 * Symfony transport is rebuilt per send from the integration row, so a
 * clinic's mail never rides the platform transport. The caller decides
 * whether a missing tenant row means "skip" or "fall back to platform mail".
 */
class EmailSender implements ChannelSender
{
    public function send(TenantIntegration $integration, string $event, array $payload): DeliveryResult
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $secrets = is_array($integration->secrets) ? $integration->secrets : [];

        $host = (string) ($config['host'] ?? '');
        $port = (int) ($config['port'] ?? 587);
        $fromAddress = (string) ($config['fromAddress'] ?? '');
        $username = (string) ($secrets['username'] ?? '');
        $password = (string) ($secrets['password'] ?? '');
        $encryption = strtolower((string) ($config['encryption'] ?? 'tls'));

        $to = (string) ($payload['to'] ?? '');
        $subject = (string) ($payload['subject'] ?? "PolytronX Enterprise PACS & RIS notification ({$event})");
        $body = (string) ($payload['message'] ?? '');

        if ($to === '' || $body === '') {
            return DeliveryResult::skipped('Email send needs both `to` and `message` in the payload.');
        }

        $start = microtime(true);

        try {
            // Real SMTP transport from the TENANT's credentials — not platform .env.
            $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                $host,
                $port,
                $encryption !== 'none'
            );

            if ($username !== '') {
                $transport->setUsername($username);
                $transport->setPassword($password);
            }

            $mailer = new \Symfony\Component\Mailer\Mailer($transport);

            $email = (new \Symfony\Component\Mime\Email())
                ->from($fromAddress !== '' ? $fromAddress : $username)
                ->to($to)
                ->subject($subject)
                ->text($body);

            $mailer->send($email);
        } catch (\Throwable $e) {
            return DeliveryResult::failed('SMTP delivery failed: '.$e->getMessage(), (int) round((microtime(true) - $start) * 1000));
        }

        return DeliveryResult::sent("Email handed to {$host}:{$port} for {$to}.", (int) round((microtime(true) - $start) * 1000));
    }

    /**
     * Is a tenant SMTP integration fully configured (so sends can bypass the
     * platform mailer)? Used by the dispatcher to route email correctly.
     */
    public static function tenantHasSmtp(TenantIntegration $integration): bool
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $secrets = is_array($integration->secrets) ? $integration->secrets : [];

        return ($config['host'] ?? '') !== ''
            && (int) ($config['port'] ?? 0) > 0
            && ($config['fromAddress'] ?? '') !== ''
            && ($secrets['username'] ?? '') !== ''
            && ($secrets['password'] ?? '') !== '';
    }
}
