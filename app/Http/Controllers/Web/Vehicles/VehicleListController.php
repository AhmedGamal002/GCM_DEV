<?php

namespace App\Http\Controllers\Web\Vehicles;

use App\Http\Controllers\Controller;

/**
 * Blade shell only — the list page fetches everything client-side from
 * /api/v1/vehicles. Same pattern as Web\Users\UserListController.
 */
class VehicleListController extends Controller
{
    public function index()
    {
        return view('tenant.vehicles.list');
    }
}
