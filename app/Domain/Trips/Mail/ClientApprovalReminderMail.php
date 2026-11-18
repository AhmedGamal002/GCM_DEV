<?php

namespace App\Domain\Trips\Mail;

use App\Models\Trip;
use App\Models\User;
use App\Support\Brand;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * "A trip is waiting for your approval" — the reminder GCM staff send to the
 * client (decision ج17, part 7.3). It names the trip and its project and links
 * to the trip's page; it never carries a cost or a document number (the client
 * doesn't see those — ج18).
 */
class ClientApprovalReminderMail extends Mailable
{
    public function __construct(
        public readonly Trip $trip,
        public readonly User $recipient,
        public readonly string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Trip :code is waiting for your approval — :app', ['code' => $this->trip->code, 'app' => Brand::name()]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.trip-reminder');
    }
}
