<?php

namespace App\Http\Controllers\Web\Assets;

use App\Http\Controllers\Controller;

/**
 * Blade shell only — the list page fetches everything from
 * /api/v1/assets. Same pattern as Web\Vehicles\VehicleListController.
 */
class AssetListController extends Controller
{
    public function index()
    {
        return view('tenant.assets.list');
    }
}
