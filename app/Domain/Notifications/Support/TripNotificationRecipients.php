<?php

namespace App\Domain\Notifications\Support;

use App\Domain\Trips\Support\ClientApproverResolver;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who a trip notification goes to — the "الدور الذي يظهر عنده الاشعار" column of the FRD table (part 8.2). An account
 * that is deactivated gets nothing; one on vacation still does (it only can't write).
 *
 * Three audiences, each a rule of its own so the table maps onto them one to one:
 *  - GCM staff: the System Admin and Data Entry (the FRD names no one else; the Auditor is not a recipient);
 *  - the client: the project's manager / auditor accounts — the default approver for "needs your approval"
 *    (ج6/ج17, ClientApproverResolver), every account that sees the project for the rest;
 *  - the contractor: the contractor users of the contractor whose driver OR vehicle is on the trip (ج19).
 */
final class TripNotificationRecipients
{
    private const LIVE = ['active', 'on_vacation'];

    public function __construct(private readonly ClientApproverResolver $approvers) {}

    /** System Admin and Data Entry of the trip's tenant. @return Collection<int, User> */
    public function staff(Trip $trip): Collection
    {
        return User::query()->withoutGlobalScopes()
            ->where('tenant_id', $trip->tenant_id)
            ->whereIn('status', self::LIVE)
            ->whereHas('roles', fn ($r) => $r->whereIn('name', ['system_admin', 'data_entry']))
            ->get();
    }

    /** The client account(s) asked to approve: the default approver, else every account that can act. @return Collection<int, User> */
    public function clientApprovers(Trip $trip): Collection
    {
        return $this->approvers->reminderRecipients($trip->loadMissing('project', 'clientApprover'));
    }

    /**
     * Every client manager / auditor who sees the trip's project — an account on vacation too: it can't act, but it
     * may read (so, unlike approversOf(), the status is "not deactivated"). @return Collection<int, User>
     */
    public function clientAccounts(Trip $trip): Collection
    {
        $project = $trip->loadMissing('project')->project;

        return User::query()->withoutGlobalScopes()
            ->where('tenant_id', $trip->tenant_id)
            ->where('company_id', $project->company_id)
            ->whereIn('status', self::LIVE)
            ->whereHas('roles', fn ($r) => $r->whereIn('name', User::CLIENT_ROLES))
            ->where(fn ($q) => $q
                ->where('all_projects', true)
                ->orWhereIn('id', DB::table('project_user')->where('project_id', $project->id)->select('user_id')))
            ->get();
    }

    /** Contractor users of the contractor(s) owning the trip's driver or vehicle. @return Collection<int, User> */
    public function contractorUsers(Trip $trip): Collection
    {
        $trip->loadMissing('driver', 'vehicle');
        $contractors = array_values(array_filter([$trip->driver?->contractor_id, $trip->vehicle?->contractor_id]));

        if ($contractors === []) {
            return new Collection;
        }

        return User::query()->withoutGlobalScopes()
            ->where('tenant_id', $trip->tenant_id)
            ->whereIn('status', self::LIVE)
            ->whereIn('contractor_id', $contractors)
            ->whereHas('roles', fn ($r) => $r->where('name', User::CONTRACTOR_ROLE))
            ->get();
    }
}
