<?php

namespace App\Http\Controllers\Web\Vehicles;

use App\Http\Controllers\Controller;
use App\Models\VehicleCategory;
use Illuminate\Support\Facades\Gate;

/**
 * Blade shells only for the vehicle categories management pages
 * (system_admin only). List / create / edit — data via
 * /api/v1/vehicle-categories. The Gate call is a UX guard (a direct URL
 * gets a 403 page instead of a page whose data calls all fail); the API's
 * own policy is the real enforcement.
 */
class VehicleCategoryController extends Controller
{
    public function list()
    {
        Gate::authorize('viewAny', VehicleCategory::class);

        return view('tenant.vehicle-categories.list');
    }

    public function add()
    {
        Gate::authorize('create', VehicleCategory::class);

        return view('tenant.vehicle-categories.add');
    }

    public function edit(int $category)
    {
        Gate::authorize('viewAny', VehicleCategory::class);

        return view('tenant.vehicle-categories.edit', ['categoryId' => $category]);
    }
}
