<?php

namespace App\Http\Controllers\Web\Contractors;

use App\Http\Controllers\Controller;

/**
 * Blade shells only, per the FRD's dedicated "contractor details" and "edit
 * contractor" pages. Both fetch/save against /api/v1/contractors/{id}; the
 * {contractor} param isn't route-model-bound (same SubstituteBindings-before-
 * tenant reason as ContractorController), so authorization happens through
 * the API call, not here.
 */
class ContractorAccountController extends Controller
{
    public function view(int $contractor)
    {
        return view('tenant.contractors.view', ['contractorId' => $contractor]);
    }

    public function edit(int $contractor)
    {
        return view('tenant.contractors.edit', ['contractorId' => $contractor]);
    }
}
