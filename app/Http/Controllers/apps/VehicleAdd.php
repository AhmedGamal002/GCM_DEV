<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;

/**
 * Blade shell only — a dedicated multi-section page, same "Multi Column
 * with Form Separator" pattern as UserAdd. Creation happens via
 * POST /api/v1/vehicles.
 */
class VehicleAdd extends Controller
{
    public function index()
    {
        return view('content.apps.app-vehicle-add');
    }
}
