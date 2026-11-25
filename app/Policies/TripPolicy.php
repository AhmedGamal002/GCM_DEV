<?php

namespace App\Policies;

use App\Domain\Trips\Support\ClientApproverResolver;
use App\Domain\Trips\Support\TripFieldVisibility;
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
 * Part 7.5 — the client's project manager and project auditor (FRD §1.4:
 * "عرض / اعتماد / رفض / تصدير") view and export the trips of the projects
 * they see (Trip::scopeVisibleTo), and approve / reject one awaiting their
 * approval (clientReview — ClientApproverResolver, ج6; the action re-checks
 * the account and the status under the row lock).
 *
 * The driver's and contractor's own trip pages open their roles here in
 * their parts (7.6 / 7.7 B). Trip::scopeVisibleTo and TripResource (ج18)
 * already handle those roles, so a part opens its role and adds its tests.
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
        // Part 7.7: the contractor user too — only the trips of his drivers and vehicles (Trip::scopeVisibleTo, ج19).
        return $actor->hasAnyRole([...Trip::STAFF_VIEW_ROLES, ...User::CLIENT_ROLES, 'driver', 'contractor_user']);
    }

    public function view(User $actor, Trip $trip): bool
    {
        return $this->viewAny($actor) && $trip->isVisibleTo($actor);
    }

    public function export(User $actor): bool
    {
        // The contractor user exports his list like the client (FRD §6: view + export); the driver has no export.
        return $actor->hasAnyRole([...Trip::STAFF_VIEW_ROLES, ...User::CLIENT_ROLES, 'contractor_user']);
    }

    /**
     * Part 8.1 — open one generated document of `$trip` (TripDocuments kind): staff and the client's
     * accounts both documents, the driver and the contractor user the delivery note
     * (TripFieldVisibility::documentKinds) — of a trip they see. Not tied to viewAny: the driver's and
     * contractor's trip pages come in 7.6 / 7.7, but their delivery note is 8.1's. Whether the trip has
     * the document yet (completed, not supplying) is the controller's 409 / 404.
     */
    public function viewDocument(User $actor, Trip $trip, string $kind): bool
    {
        return in_array($kind, TripFieldVisibility::documentKinds($actor), true) && $trip->isVisibleTo($actor);
    }

    /** Part 8.1 — the combined PDF of the trip's documents: GCM staff only (د10 (2)). */
    public function exportDocuments(User $actor, Trip $trip): bool
    {
        return TripFieldVisibility::exportsDocuments($actor) && $trip->isVisibleTo($actor);
    }

    /** The driver's own create form (FRD §1.14 "from the driver's point of view", part 7.6) — a driver account with a driver record. */
    public function createAsDriver(User $actor): bool
    {
        return $actor->hasRole('driver') && $actor->driver !== null;
    }

    /** The driver adds the recycle receipt photo to HIS trip (ج13: until it is completed — the action checks the status). */
    public function attachRecycleReceipt(User $actor, Trip $trip): bool
    {
        return $this->createAsDriver($actor) && $trip->isVisibleTo($actor);
    }

    /** Approve or reject `$trip` for the client (ج6). The status is the action's check (409). */
    public function clientReview(User $actor, Trip $trip): bool
    {
        return $actor->isClient() && app(ClientApproverResolver::class)->canApprove($actor, $trip);
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
