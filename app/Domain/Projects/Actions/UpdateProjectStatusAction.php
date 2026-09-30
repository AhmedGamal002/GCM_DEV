<?php

namespace App\Domain\Projects\Actions;

use App\Models\Project;
use App\Models\User;

/**
 * FRD V01.14 §1.12.4: deactivating / re-activating a project is (System
 * Admin / Data Entry).
 *
 * The FRD attaches no consequence to it beyond the status, and nothing
 * cascades: not to the company (deactivating a company doesn't touch its
 * projects either), not to the assets currently in the project. A
 * deactivated project is simply no longer offered for new work — see the
 * "insert an asset into a project" page.
 */
class UpdateProjectStatusAction
{
    public function execute(Project $project, string $status, User $actor): Project
    {
        // operational_status is outside $fillable — set explicitly.
        $project->operational_status = $status;
        $project->updated_by = $actor->id;
        $project->save();

        return $project->load(['company', 'updatedBy']);
    }
}
