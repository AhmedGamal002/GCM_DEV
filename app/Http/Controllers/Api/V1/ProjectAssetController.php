<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Assets\Actions\InsertAssetIntoProjectAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assets\InsertAssetIntoProjectRequest;
use App\Http\Resources\AssetResource;
use App\Models\Project;

/**
 * FRD V01.14 §1.7.3 "Insert an asset into a project". Looked up by raw id
 * inside the method, after the 'tenant' middleware — same reasoning as
 * ProjectController. Listing a project's assets is just
 * GET /api/v1/assets?project_id=…
 */
class ProjectAssetController extends Controller
{
    public function store(InsertAssetIntoProjectRequest $request, int $project, InsertAssetIntoProjectAction $action)
    {
        $project = Project::findOrFail($project);

        $asset = $action->execute($project, $request->validated('asset_id'), $request->user());

        return AssetResource::make($asset)->response()->setStatusCode(201);
    }
}
