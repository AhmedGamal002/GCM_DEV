<?php

namespace App\Domain\Users\Actions;

use App\Models\Company;
use App\Models\Project;
use App\Models\User;

/**
 * Applies a client account's company + project access (FRD V01.14 §1.4)
 * and keeps the representative fields honest.
 *
 * "All projects" is a flag on the account (so projects created later are
 * covered automatically); "specific projects" is the project_user list.
 *
 * The company (§1.11) and project (§1.12) "representative account" fields
 * point at one of these accounts. If an edit takes the account out of that
 * role — another company, no longer a project manager, no longer having the
 * project — the field is cleared, otherwise it would keep naming someone
 * who is not the company's/project's representative any more.
 */
final class SyncClientAccess
{
    /**
     * @param  array<int, int|string>  $projectIds  only used when $scope is 'specific'
     */
    public static function apply(User $user, int $companyId, string $scope, array $projectIds): void
    {
        $user->forceFill([
            'company_id' => $companyId,
            'all_projects' => $scope === 'all',
        ])->save();

        $user->projects()->sync($scope === 'all' ? [] : array_map('intval', $projectIds));

        self::releaseLostRepresentations($user->fresh());
    }

    private static function releaseLostRepresentations(User $user): void
    {
        Company::query()
            ->where('representative_id', $user->id)
            ->get()
            ->each(function (Company $company) use ($user) {
                if ($company->id !== $user->company_id || ! $user->hasRole('client_project_manager')) {
                    $company->forceFill(['representative_id' => null])->save();
                }
            });

        Project::query()
            ->where('representative_id', $user->id)
            ->get()
            ->each(function (Project $project) use ($user) {
                if (! $project->isVisibleTo($user)) {
                    $project->forceFill(['representative_id' => null])->save();
                }
            });
    }
}
