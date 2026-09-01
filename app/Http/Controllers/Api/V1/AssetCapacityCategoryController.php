<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssetCapacityCategoryResource;
use App\Models\AssetCapacityCategory;

/**
 * Read-only for now — feeds the vehicle form's "embedded container
 * capacity" dropdown. Full CRUD arrives with the Assets module. Rows are
 * tenant-scoped by BelongsToTenant.
 */
class AssetCapacityCategoryController extends Controller
{
    public function index()
    {
        return AssetCapacityCategoryResource::collection(
            AssetCapacityCategory::orderBy('name')->get()
        );
    }
}
