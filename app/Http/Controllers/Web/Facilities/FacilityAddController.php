<?php

namespace App\Http\Controllers\Web\Facilities;

use App\Http\Controllers\Controller;

/**
 * Blade shell only — the form posts to /api/v1/facilities.
 */
class FacilityAddController extends Controller
{
    public function index()
    {
        return view('tenant.facilities.add');
    }
}
