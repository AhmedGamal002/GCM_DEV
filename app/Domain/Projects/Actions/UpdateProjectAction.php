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

        // Outside $fillable — the request already validated it.
        if (array_key_exists('representative_id', $data)) {
            $project->representative_id = $data['representative_id'];
        }

        $project->updated_by = $actor->id;
        $project->save();

        return $project->load(['company', 'representative', 'updatedBy']);
    }
}
