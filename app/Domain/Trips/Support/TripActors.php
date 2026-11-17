<?php

namespace App\Domain\Trips\Support;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Who may drive each trip action — checked by the actions themselves, not
 * only by the routes' policy (the security checklist of part 7.1: "every
 * transition action checks the role and the scope"):
 *
 *  - GCM staff who manage trips: system admin and data entry (FRD: creating,
 *    editing, the final approval, the objection review and cancelling). The
 *    auditor is view-only.
 *  - the driver: creates their own trips only, and afterwards only attaches
 *    the recycle receipt photo of their own trip (FRD: no other edit).
 *  - the client: ClientApproverResolver (any manager or auditor of the
 *    project's company who sees the project, ج6).
 */
final class TripActors
{
    public const STAFF_ROLES = ['system_admin', 'data_entry'];

    public static function isStaff(User $user): bool
    {
        return $user->hasAnyRole(self::STAFF_ROLES);
    }

    public static function assertStaff(User $user): void
    {
        if (! self::isStaff($user)) {
            throw new AuthorizationException(__('Only the system admin or data entry can do this to a trip.'));
        }
    }

    public static function assertDriver(User $user): void
    {
        if (! $user->hasRole('driver') || $user->driver === null) {
            throw new AuthorizationException(__('Only a driver can create a trip from the driver\'s page.'));
        }
    }

    public static function assertDriverOf(User $user, Trip $trip): void
    {
        self::assertDriver($user);

        if ((int) $user->driver->id !== (int) $trip->driver_id) {
            throw new AuthorizationException(__('This trip belongs to another driver.'));
        }
    }

    public static function assertClientApprover(User $user, Trip $trip): void
    {
        if (! app(ClientApproverResolver::class)->canApprove($user, $trip)) {
            throw new AuthorizationException(__('Only an active account of the client that sees this project can approve or reject its trips.'));
        }
    }
}
