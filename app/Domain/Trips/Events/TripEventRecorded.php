<?php

namespace App\Domain\Trips\Events;

use App\Models\TripEvent;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something happened to a trip and was written to its history — one event
 * per trip_events row (creation, the client's approval or rejection, an
 * edit, the final approval, a cancellation, …; the row's `type` says which).
 * Fired once the transaction commits, so a rolled-back action notifies
 * nobody. Part 8.2 listens to it for the in-app notifications (open point
 * 9.1 of PARALLEL_WORK_PHASE3.md, answered in 7.1: yes, one event per
 * transition, carrying the history row).
 */
class TripEventRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly TripEvent $event) {}
}
