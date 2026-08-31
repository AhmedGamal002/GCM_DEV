<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;

/**
 * Blade shells only, per the FRD's dedicated "vehicle details" and
 * "edit vehicle" pages. Both fetch/save against /api/v1/vehicles/{id};
 * the {vehicle} param isn't route-model-bound (same
 * SubstituteBindings-before-tenant reason as VehicleController), so
 * authorization happens through the API call, not here.
 */
class VehicleAccount extends Controller
{
    public function view(int $vehicle)
    {
        return view('content.apps.app-vehicle-view', ['vehicleId' => $vehicle]);
    }

    public function edit(int $vehicle)
    {
        return view('content.apps.app-vehicle-edit', ['vehicleId' => $vehicle]);
    }
}
