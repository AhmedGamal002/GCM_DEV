<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VehicleCategoryResource;
use App\Models\VehicleCategory;

/**
 * Read-only reference data for the vehicle form's category dropdown.
 * Every tenant role may read it; there is no mutation endpoint — the
 * five categories are a fixed, seeded list.
 */
class VehicleCategoryController extends Controller
{
    public function index()
    {
        return VehicleCategoryResource::collection(VehicleCategory::orderBy('id')->get());
    }
}
