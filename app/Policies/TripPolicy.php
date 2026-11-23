<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

/**
 * Trips (FRD V01.14 §1.14).
 *
 * Part 7.4 — GCM's trips pages: managing trips (create, edit, the staff
 * transitions — final approval, the client's objection, cancelling) is
 * System Admin + Data Entry; the Auditor views and exports (the FRD role
 * pages). Every single-trip ability also needs Trip::isVisibleTo() — the one
 * visibility rule (staff: all; client / driver / contractor: theirs).
 *
 * The client's, driver's and contractor's own trip pages open their roles
 * here in their parts (7.5 A, 7.6 / 7.7 B): viewAny / view are staff-only
 * until then. Trip::scopeVisibleTo and TripResource (ج18) already handle
 * those roles, so a part opens its role and adds its tests.
 *
 * Part 7.2 — the trip form's lists:
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
    private const MANAGERS = ['system_admin', 'data_entry'];

    public function viewAny(User $actor): bool
    {
        return $actor->hasAnyRole(Trip::STAFF_VIEW_ROLES);
    }

    public function view(User $actor, Trip $trip): bool
    {
        return $this->viewAny($actor) && $trip->isVisibleTo($actor);
    }

    public function export(User $actor): bool
    {
        return $actor->hasAnyRole(Trip::STAFF_VIEW_ROLES);
    }

    /** Create a trip from the staff form. The driver's own create comes with 7.6. */
    public function create(User $actor): bool
    {
        return $actor->hasAnyRole(self::MANAGERS);
    }

    /** Edit a trip and drive the staff transitions (the actions re-check the status). */
    public function update(User $actor, Trip $trip): bool
    {
        return $this->manage($actor) && $trip->isVisibleTo($actor);
    }

    public function manage(User $actor): bool
    {
        return $actor->hasAnyRole(self::MANAGERS);
    }

    public function pickOptions(User $actor): bool
    {
        return $actor->hasAnyRole([...self::MANAGERS, 'driver']);
    }

    public function pickStaffOptions(User $actor): bool
    {
        return $actor->hasAnyRole(self::MANAGERS);
    }

    /** Whether a form editing `$trip` may ask for lists around it (its own containers stay free for it). */
    public function pickOptionsFor(User $actor, Trip $trip): bool
    {
        return $this->pickStaffOptions($actor);
    }
}
