<?php

namespace App\Http\Controllers\Web\Facilities;

use App\Http\Controllers\Controller;

/**
 * Blade shell only — the list page fetches everything from
 * /api/v1/facilities. Same pattern as Web\Assets\AssetListController.
 */
class FacilityListController extends Controller
{
    public function index()
    {
        return view('tenant.facilities.list');
    }
}
