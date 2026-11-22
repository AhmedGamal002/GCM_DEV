<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

/**
 * Trips (FRD V01.14 §1.14). Part 7.2 adds the abilities of the trip form's
 * cascading lists (/api/v1/trips/options/*); the trip's own abilities (list,
 * view, create, edit, transitions) come with the API in 7.4.
 *
 *  - pickOptions: whoever fills a trip form — System Admin / Data Entry
 *    (staff form) and the Driver (his own form, §1.14 "from the driver's
 *    point of view"). The auditor, client and contractor accounts fill none.
 *  - pickStaffOptions: the lists only the staff form has — the driver to
 *    choose, the project's POs (number, status and the line's per-trip cost:
 *    the staff form shows the cost read-only, WEEKLY_PLAN 7.2 item 8 — never
 *    the balances, and Data Entry still has no /purchase-orders access), and
 *    the default approvers.
 */
class TripPolicy
{
    public function pickOptions(User $actor): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry', 'driver']);
    }

    public function pickStaffOptions(User $actor): bool
    {
        return $actor->hasAnyRole(['system_admin', 'data_entry']);
    }

    /** Whether a form editing `$trip` may ask for lists around it (its own containers stay free for it). */
    public function pickOptionsFor(User $actor, Trip $trip): bool
    {
        return $this->pickStaffOptions($actor);
    }
}
