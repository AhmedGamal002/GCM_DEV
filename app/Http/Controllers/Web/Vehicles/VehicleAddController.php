<?php

namespace App\Http\Controllers\Web\Vehicles;

use App\Http\Controllers\Controller;

/**
 * Blade shell only — a dedicated multi-section page, same "Multi Column
 * with Form Separator" pattern as Web\Users\UserAddController. Creation
 * happens via POST /api/v1/vehicles.
 */
class VehicleAddController extends Controller
{
    public function index()
    {
        return view('tenant.vehicles.add');
    }
}
