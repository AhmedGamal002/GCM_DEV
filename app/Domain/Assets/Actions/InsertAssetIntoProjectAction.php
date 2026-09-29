<?php

namespace App\Domain\Assets\Actions;

use App\Models\Asset;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * FRD V01.14 §1.7.3 "Insert an asset into a project": leaving a container
 * / tank at a project site takes it out of the pool of available assets,
 * so the asset balances match reality from day one.
 *
 * An asset can only be inserted when it is available — active and not
 * already in a project (an asset in maintenance or deactivated is "not
 * available for trips" per the FRD) — and the project must be active.
 * Both are re-checked under a row lock so two people inserting the same
 * asset at once can't both win. Taking an asset back out is not defined
 * by the FRD yet (it comes with trips, a later phase).
 */
class InsertAssetIntoProjectAction
{
    public function execute(Project $project, int $assetId, User $actor): Asset
    {
        if ($project->operational_status !== 'active') {
            throw ValidationException::withMessages([
                'project' => __('Assets can only be inserted into an active project.'),
            ]);
        }

        return DB::transaction(function () use ($project, $assetId, $actor) {
            $asset = Asset::whereKey($assetId)->lockForUpdate()->firstOrFail();

            if ($asset->operational_status !== 'active') {
                throw ValidationException::withMessages([
                    'asset_id' => __('Only an active asset can be inserted into a project.'),
                ]);
            }

            if ($asset->project_id !== null) {
                throw ValidationException::withMessages([
                    'asset_id' => __('This asset is already in a project.'),
                ]);
            }

            // project_id is outside $fillable — set explicitly.
            $asset->project_id = $project->id;
            $asset->updated_by = $actor->id;
            $asset->save();

            return $asset->load(['capacityCategory', 'compatibleVehicleCategories', 'project', 'updatedBy']);
        });
    }
}
