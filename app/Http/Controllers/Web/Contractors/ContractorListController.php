<?php

namespace App\Http\Controllers\Web\Contractors;

use App\Http\Controllers\Controller;

/**
 * Blade shell only — the list page fetches everything from
 * /api/v1/contractors. Same pattern as Web\Companies\CompanyListController.
 */
class ContractorListController extends Controller
{
    public function index()
    {
        return view('tenant.contractors.list');
    }
}
