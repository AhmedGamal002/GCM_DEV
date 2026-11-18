<?php

namespace App\Domain\Otp\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Everything that can go wrong asking for or entering a one-time code. Each
 * one knows how it answers an API call: a message the user can read, a
 * machine `code` the page switches on (the "enter your code" box reacts to
 * `otp_required`, `otp_invalid`, `otp_locked`, ...) and the HTTP status.
 */
class OtpException extends RuntimeException
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 422,
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    /** The account has not entered a code in this session (the guard's answer). */
    public static function required(): self
    {
        return new self(__('Enter the verification code we e-mailed you to continue.'), 'otp_required', 403);
    }

    public static function resendTooSoon(int $seconds): self
    {
        return new self(
            trans_choice('{1} You can ask for a new code in :count second.|[2,*] You can ask for a new code in :count seconds.', $seconds),
            'otp_resend_too_soon',
            429,
            ['retry_after' => $seconds],
        );
    }

    /** No code is waiting to be entered (none sent, or it was replaced / used up). */
    public static function notSent(): self
    {
        return new self(__('Ask for a verification code first.'), 'otp_not_sent');
    }

    public static function expired(): self
    {
        return new self(__('This code has expired. Ask for a new one.'), 'otp_expired');
    }

    public static function invalid(int $attemptsLeft): self
    {
        return new self(
            trans_choice('{1} The code is not correct. You have :count try left.|[2,*] The code is not correct. You have :count tries left.', $attemptsLeft),
            'otp_invalid',
            422,
            ['attempts_left' => $attemptsLeft],
        );
    }

    /** Too many wrong tries: this code is dead, a new one has to be asked for. */
    public static function locked(): self
    {
        return new self(__('Too many wrong tries. Ask for a new code.'), 'otp_locked', 429);
    }

    public static function cannotSend(): self
    {
        return new self(__('We could not send the e-mail right now. Please try again in a moment.'), 'otp_mail_failed', 503);
    }

    public function render(Request $request): JsonResponse
    {
        $body = ['message' => $this->getMessage(), 'code' => $this->errorCode] + $this->extra;

        if ($this->errorCode === 'otp_invalid') {
            // The same shape as a validation error, so the form puts the message under the field.
            $body['errors'] = ['code' => [$this->getMessage()]];
        }

        $response = response()->json($body, $this->status);

        if (isset($this->extra['retry_after'])) {
            $response->header('Retry-After', (string) $this->extra['retry_after']);
        }

        return $response;
    }
}
