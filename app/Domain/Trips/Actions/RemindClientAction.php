<?php

namespace App\Domain\Trips\Actions;

use App\Domain\Trips\Exceptions\TripReminderException;
use App\Domain\Trips\Mail\ClientApprovalReminderMail;
use App\Domain\Trips\Support\ClientApproverResolver;
use App\Domain\Trips\Support\TripHistory;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * "Remind the client to approve" — the button on a trip that is awaiting the
 * client's decision (FRD V01.14 "عرض تفاصيل الرحلة", decision ج17, part 7.3).
 *
 *  - only while the trip is `awaiting_client_approval`;
 *  - the e-mail goes to the trip's default client approver when that account
 *    can still act, otherwise to every client account that can
 *    (ClientApproverResolver::reminderRecipients — nobody → refused);
 *  - no sooner than `trips.reminder_interval_minutes` (one hour) after the
 *    last reminder of the same trip, read from the trip's own history;
 *  - every reminder is written to that history (type client_reminded, with who
 *    was mailed) — so the gap survives restarts, and GCM sees who asked when;
 *  - it asks for NO one-time code (ج17).
 *
 * The trip row is locked while the gap is checked and the reminder recorded,
 * so two clicks at once send one e-mail; if the e-mail can't be sent nothing
 * is recorded and the button can be pressed again at once.
 */
class RemindClientAction
{
    public function __construct(private readonly ClientApproverResolver $approvers) {}

    /**
     * @return array{sent_to: int, next_reminder_at: \Carbon\CarbonInterface}
     *
     * @throws TripReminderException
     */
    public function execute(Trip $trip, User $actor): array
    {
        try {
            return DB::transaction(function () use ($trip, $actor) {
                $trip = Trip::query()->whereKey($trip->id)->lockForUpdate()->firstOrFail();

                if ($trip->status !== Trip::STATUS_AWAITING_CLIENT_APPROVAL) {
                    throw TripReminderException::notAwaitingApproval();
                }

                $recipients = $this->approvers->reminderRecipients($trip);

                if ($recipients->isEmpty()) {
                    throw TripReminderException::nobodyToRemind();
                }

                $interval = (int) config('trips.reminder_interval_minutes');
                $last = TripEvent::query()->where('trip_id', $trip->id)->where('type', TripEvent::CLIENT_REMINDED)->latest('id')->first();

                if ($last !== null && $last->created_at->gt(now()->subMinutes($interval))) {
                    $left = (int) ceil(now()->diffInSeconds($last->created_at->copy()->addMinutes($interval), true) / 60);

                    throw TripReminderException::tooSoon(max(1, $left));
                }

                $trip->loadMissing('project');
                $url = url('/app/trip/view/'.$trip->id);

                // Written in the same transaction as the e-mails: a failure sends nothing and records nothing.
                TripHistory::record($trip, TripEvent::CLIENT_REMINDED, $actor, [
                    'changes' => ['recipients' => $recipients->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->all()],
                ]);

                foreach ($recipients as $recipient) {
                    Mail::to($recipient->email)->send(
                        (new ClientApprovalReminderMail($trip, $recipient, $url))->locale(app()->getLocale())
                    );
                }

                return ['sent_to' => $recipients->count(), 'next_reminder_at' => now()->addMinutes($interval)];
            });
        } catch (TripReminderException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            throw TripReminderException::cannotSend();
        }
    }
}
