<?php

namespace App\Domain\Notifications\Support;

/**
 * The kinds of in-app notification (FRD V01.14 "الاشعارات والايميلات التلقائية", part 8.2): the six about a trip's
 * state plus the contract alert (decision ب9 / 2026-11-09) in its two forms. The sentence is written when the
 * notification is read, in the reader's language, from the row's `data` — never stored.
 */
final class NotificationType
{
    public const TRIP_CREATED = 'trip_created';

    public const TRIP_UPDATED = 'trip_updated';

    public const TRIP_CLIENT_APPROVED = 'trip_client_approved';

    public const TRIP_CLIENT_REJECTED = 'trip_client_rejected';

    public const TRIP_FINAL_APPROVED = 'trip_final_approved';

    public const TRIP_CANCELLED = 'trip_cancelled';

    public const PO_NEAR_EXHAUSTION = 'po_near_exhaustion';

    public const PO_EXHAUSTED = 'po_exhausted';

    public const ALL = [
        self::TRIP_CREATED, self::TRIP_UPDATED, self::TRIP_CLIENT_APPROVED, self::TRIP_CLIENT_REJECTED,
        self::TRIP_FINAL_APPROVED, self::TRIP_CANCELLED, self::PO_NEAR_EXHAUSTION, self::PO_EXHAUSTED,
    ];

    /** The "notification type" column. */
    public static function label(string $type): string
    {
        return match ($type) {
            self::TRIP_CREATED => __('New trip awaiting client approval'),
            self::TRIP_UPDATED => __('Trip data updated'),
            self::TRIP_CLIENT_APPROVED => __('Trip approved by the client'),
            self::TRIP_CLIENT_REJECTED => __('Trip rejected by the client'),
            self::TRIP_FINAL_APPROVED => __('Trip finally approved'),
            self::TRIP_CANCELLED => __('Trip cancelled'),
            self::PO_NEAR_EXHAUSTION => __('Contract about to run out'),
            self::PO_EXHAUSTED => __('Contract exhausted'),
            default => $type,
        };
    }

    /**
     * The "notification details" column. Only what the recipient may know: no cost, no PO number for a trip.
     *
     * @param  array<string, mixed>  $data
     */
    public static function detail(string $type, array $data): string
    {
        $code = (string) ($data['code'] ?? '');

        return match ($type) {
            self::TRIP_CREATED => __('Trip :code was created and is awaiting the client\'s approval.', ['code' => $code]),
            self::TRIP_UPDATED => __('The recycle receipt photo was added to trip :code.', ['code' => $code]),
            self::TRIP_CLIENT_APPROVED => __('The client approved trip :code.', ['code' => $code]),
            self::TRIP_CLIENT_REJECTED => __('The client rejected trip :code.', ['code' => $code]),
            self::TRIP_FINAL_APPROVED => __('Trip :code was finally approved and is completed.', ['code' => $code]),
            self::TRIP_CANCELLED => __('Trip :code was cancelled.', ['code' => $code]),
            self::PO_NEAR_EXHAUSTION => __('Contract :po — :service is about to run out.', ['po' => (string) ($data['po'] ?? ''), 'service' => (string) ($data['service'] ?? '')]),
            self::PO_EXHAUSTED => __('Contract :po — :service has run out.', ['po' => (string) ($data['po'] ?? ''), 'service' => (string) ($data['service'] ?? '')]),
            default => '',
        };
    }

    public static function isContractAlert(string $type): bool
    {
        return in_array($type, [self::PO_NEAR_EXHAUSTION, self::PO_EXHAUSTED], true);
    }
}
