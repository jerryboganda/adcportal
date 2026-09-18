<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Patient-facing appointment reminder.
 *
 * Deliberately sent SYNCHRONOUSLY (no ShouldQueue): the production topology
 * (single VPS container, `schedule:work`, no queue worker) cannot drain a
 * database queue, so queueing these would silently strand them in `jobs`.
 * Volume is one mail per upcoming study per clinic — safe to send inline.
 */
class AppointmentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $reminder)
    {
    }

    public function envelope(): Envelope
    {
        $envelope = new Envelope(subject: $this->reminder['subject']);

        if (! empty($this->reminder['clinicEmail'])) {
            $envelope->replyTo($this->reminder['clinicEmail']);
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.appointment_reminder',
            with: ['r' => $this->reminder],
        );
    }
}
