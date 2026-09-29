<?php

namespace App\Http\Controllers\Web\Facilities;

use App\Http\Controllers\Controller;

/**
 * Blade shells only — the details and edit pages fetch/save against
 * /api/v1/facilities/{id}. The {facility} param isn't route-model-bound
 * (same SubstituteBindings-before-tenant reason as FacilityController), so
 * authorization happens through the API call, not here.
 */
class FacilityAccountController extends Controller
{
    public function view(int $facility)
    {
        return view('tenant.facilities.view', ['facilityId' => $facility]);
    }

    public function edit(int $facility)
    {
        return view('tenant.facilities.edit', ['facilityId' => $facility]);
    }
}
