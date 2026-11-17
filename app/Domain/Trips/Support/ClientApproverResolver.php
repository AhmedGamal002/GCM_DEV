<?php

namespace App\Domain\Trips\Support;

use App\Models\Project;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Decision ج6 (2026-11-09 / 2026-11-10): who approves a trip on the
 * client's side, and who is told about it.
 *
 *  1. Any project manager OR project auditor of the client company who sees
 *     the project (Project::scopeVisibleTo) and whose account is active may
 *     approve or reject it. The "default client approver" of the trip
 *     (prefilled with the project's representative) is not the only one
 *     allowed — it is who gets the notification and the reminder.
 *  2. If no client account can act (none active, or none sees the project)
 *     the trip simply stays awaiting approval; GCM staff may then approve it
 *     finally without the client (the FRD allows it — FinalApproveTripAction).
 *  3. Reminders and notifications go to the default approver when that
 *     account can still act; otherwise to every client account that can
 *     (a reading of ours — change it here if corrected).
 *
 * Part 7.3 (the "remind the client" e-mail) and 8.2 (notifications) read
 * the recipients from here; the client actions (7.1 / 7.5) ask canApprove().
 */
class ClientApproverResolver
{
    /** Whether `$user` may approve or reject `$trip` for the client. */
    public function canApprove(User $user, Trip $trip): bool
    {
        // Read from the database, not the model in hand: the account's status and projects as they are now.
        return $this->canApproveFor($user, $trip->project);
    }

    /** The client accounts that may approve the project's trips (active, of its company, seeing it). */
    public function approversOf(Project $project): Builder
    {
        return User::query()
            ->where('users.company_id', $project->company_id)
            ->where('users.status', 'active')
            ->whereHas('roles', fn (Builder $r) => $r->whereIn('name', User::CLIENT_ROLES))
            ->where(fn (Builder $q) => $q
                ->where('users.all_projects', true)
                ->orWhereIn('users.id', DB::table('project_user')->where('project_id', $project->id)->select('user_id')));
    }

    /** Whether `$user` may approve the project's trips — the same test as canApprove(), before there is a trip. */
    public function canApproveFor(?User $user, Project $project): bool
    {
        return $user !== null && $this->approversOf($project)->whereKey($user->id)->exists();
    }

    /** The default client approver of a new trip: the project's representative, when that account can approve. */
    public function defaultFor(Project $project): ?User
    {
        $representative = $project->representative;

        return $this->canApproveFor($representative, $project) ? $representative : null;
    }

    /**
     * Who is reminded to approve the trip (ج17) and notified about it (8.2):
     * the trip's default client approver if that account can still act,
     * otherwise every client account that can.
     *
     * @return Collection<int, User>
     */
    public function reminderRecipients(Trip $trip): Collection
    {
        $default = $trip->clientApprover;

        if ($this->canApproveFor($default, $trip->project)) {
            return new Collection([$default]);
        }

        return $this->approversOf($trip->project)->orderBy('users.id')->get();
    }
}
