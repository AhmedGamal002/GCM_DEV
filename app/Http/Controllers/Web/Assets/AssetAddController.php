<?php

namespace App\Http\Controllers\Web\Assets;

use App\Http\Controllers\Controller;

/**
 * Blade shell only — the create form posts to /api/v1/assets.
 * Same pattern as Web\Vehicles\VehicleAddController.
 */
class AssetAddController extends Controller
{
    public function index()
    {
        return view('tenant.assets.add');
    }
}
