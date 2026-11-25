<?php

namespace App\Domain\Notifications\Listeners;

use App\Domain\Notifications\Support\NotificationType;
use App\Domain\Notifications\Support\Notifier;
use App\Domain\Notifications\Support\TripNotificationRecipients;
use App\Domain\Trips\Events\TripEventRecorded;
use App\Models\Trip;
use App\Models\TripEvent;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Part 8.2: turns a row of a trip's history into the in-app notifications of the FRD table
 * ("الاشعارات والايميلات التلقائية"). The event fires after the transaction commits, so a rolled-back action notifies
 * nobody — and a failure here is reported and swallowed: the action it follows already succeeded.
 *
 *  history row                                  notification                      to
 *  created, awaiting the client                 new trip awaiting approval        client approvers + contractor (+ staff when the driver made it)
 *  created, completed straight away             finally approved                  client accounts + contractor
 *  recycle receipt attached (driver)            trip data updated                 staff when the driver made it
 *  client approved                              approved by the client            staff
 *  client rejected                              rejected by the client            staff when the driver made it
 *  final approval / objection rejected          finally approved                  client accounts + contractor
 *  cancelled                                    cancelled                         client accounts + contractor
 *
 * "(في حال انشائها من خلال السائق)" in the FRD covers the staff of the rows that carry it. Readings of ours: a trip
 * recorded as completed from the start and a rejected objection (which completes the trip) are "finally approved";
 * the person who caused the change is not notified of it; the other history rows (edits, reminders, asset moves…) notify nobody.
 */
class NotifyAboutTripEvent
{
    public function __construct(private readonly TripNotificationRecipients $recipients) {}

    public function handle(TripEventRecorded $recorded): void
    {
        try {
            $this->notify($recorded->event);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function notify(TripEvent $event): void
    {
        $trip = $event->trip()->withoutGlobalScopes()->first();

        if ($trip === null) {
            return;
        }

        $driverMade = $trip->source === Trip::SOURCE_DRIVER;
        $staff = fn (): Collection => $this->recipients->staff($trip);

        [$type, $to] = match ($event->type) {
            TripEvent::CREATED => $trip->status === Trip::STATUS_COMPLETED
                ? [NotificationType::TRIP_FINAL_APPROVED, $this->clientAndContractor($trip)]
                : ($trip->status === Trip::STATUS_AWAITING_CLIENT_APPROVAL
                    ? [NotificationType::TRIP_CREATED, $this->recipients->clientApprovers($trip)->merge($this->recipients->contractorUsers($trip))->merge($driverMade ? $staff() : [])]
                    : [null, null]),
            TripEvent::RECYCLE_RECEIPT_ATTACHED => [NotificationType::TRIP_UPDATED, $driverMade ? $staff() : null],
            TripEvent::CLIENT_APPROVED => [NotificationType::TRIP_CLIENT_APPROVED, $staff()],
            TripEvent::CLIENT_REJECTED => [NotificationType::TRIP_CLIENT_REJECTED, $driverMade ? $staff() : null],
            TripEvent::FINAL_APPROVED, TripEvent::OBJECTION_REJECTED => [NotificationType::TRIP_FINAL_APPROVED, $this->clientAndContractor($trip)],
            TripEvent::CANCELLED => [NotificationType::TRIP_CANCELLED, $this->clientAndContractor($trip)],
            default => [null, null],
        };

        if ($type === null || $to === null) {
            return;
        }

        Notifier::send(
            (int) $trip->tenant_id,
            $type,
            new Collection($to instanceof Collection ? $to->all() : $to),
            ['code' => $trip->code],
            tripId: $trip->id,
            subject: $trip->code,
            exceptUserId: $event->actor_id,
        );
    }

    private function clientAndContractor(Trip $trip): Collection
    {
        return $this->recipients->clientAccounts($trip)->merge($this->recipients->contractorUsers($trip));
    }
}
