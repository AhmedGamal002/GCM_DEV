<?php

namespace App\Domain\Projects\Actions;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateProjectAction
{
    /**
     * @param  array<string, mixed>  $data  validated input (incl. company_id, operational_status)
     */
    public function execute(array $data, User $actor): Project
    {
        return DB::transaction(function () use ($data, $actor) {
            // tenant_id, sequence and code are stamped by BelongsToTenant / Project::booted().
            $project = new Project($data);

            // company_id and operational_status are outside $fillable — set explicitly.
            $project->company_id = $data['company_id'];
            $project->operational_status = $data['operational_status'] ?? 'active';
            $project->updated_by = $actor->id;
            $project->save();

            return $project->load(['company', 'updatedBy']);
        });
    }
}
