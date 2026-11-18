<?php

namespace App\Domain\Trips\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Why "remind the client to approve" (decision ج17, part 7.3) was refused —
 * each with a message the button can show as it is.
 */
class TripReminderException extends RuntimeException
{
    /** @param array<string, mixed> $extra */
    private function __construct(string $message, public readonly string $errorCode, public readonly int $status, public readonly array $extra = [])
    {
        parent::__construct($message);
    }

    /** Only a trip waiting for the client's decision can be reminded about. */
    public static function notAwaitingApproval(): self
    {
        return new self(__('Only a trip that is awaiting the client\'s approval can be reminded.'), 'reminder_not_awaiting', 422);
    }

    /** No client account can act on it: nobody to remind (GCM may approve it finally without the client). */
    public static function nobodyToRemind(): self
    {
        return new self(__('No active client account can approve this trip, so there is nobody to remind.'), 'reminder_no_recipient', 422);
    }

    public static function tooSoon(int $minutes): self
    {
        return new self(
            trans_choice('{1} A reminder was sent a moment ago. You can send another in :count minute.|[2,*] A reminder was sent recently. You can send another in :count minutes.', $minutes),
            'reminder_too_soon',
            429,
            ['retry_after_minutes' => $minutes],
        );
    }

    public static function cannotSend(): self
    {
        return new self(__('We could not send the e-mail right now. Please try again in a moment.'), 'reminder_mail_failed', 503);
    }

    public function render(Request $request): JsonResponse
    {
        $response = response()->json(['message' => $this->getMessage(), 'code' => $this->errorCode] + $this->extra, $this->status);

        if (isset($this->extra['retry_after_minutes'])) {
            $response->header('Retry-After', (string) ($this->extra['retry_after_minutes'] * 60));
        }

        return $response;
    }
}
