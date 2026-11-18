<?php

namespace App\Domain\Otp\Mail;

use App\Models\User;
use App\Support\Brand;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The e-mail that carries a one-time code (FRD "ايميل OTP"). Sent in the
 * language of the request that asked for it; the code is the only secret in
 * it, and it says how long it is good for and what to do if it wasn't asked for.
 */
class OtpCodeMail extends Mailable
{
    public function __construct(
        public readonly User $user,
        public readonly string $code,
        public readonly int $ttlMinutes,
        public readonly ?string $purpose = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Your verification code — :app', ['app' => Brand::name()]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.otp');
    }
}
