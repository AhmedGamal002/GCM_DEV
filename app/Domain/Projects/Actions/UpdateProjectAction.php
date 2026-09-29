<?php

namespace App\Domain\Projects\Actions;

use App\Models\Project;
use App\Models\User;

/**
 * FRD V01.14 §1.12.4: every project field is editable by (System Admin /
 * Data Entry) except the owning company. operational_status has its own
 * action.
 */
class UpdateProjectAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Project $project, array $data, User $actor): Project
    {
        $project->fill($data);
        $project->updated_by = $actor->id;
        $project->save();

        return $project->load(['company', 'updatedBy']);
    }
}
