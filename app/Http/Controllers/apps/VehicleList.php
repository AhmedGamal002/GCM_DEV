<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;

/**
 * Blade shell only — the list page fetches everything client-side from
 * /api/v1/vehicles. Same pattern as UserList.
 */
class VehicleList extends Controller
{
    public function index()
    {
        return view('content.apps.app-vehicle-list');
    }
}
