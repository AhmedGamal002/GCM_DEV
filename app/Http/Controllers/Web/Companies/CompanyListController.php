<?php

namespace App\Http\Controllers\Web\Companies;

use App\Http\Controllers\Controller;

/**
 * Blade shell only — the list page fetches everything from
 * /api/v1/companies. Same pattern as Web\Assets\AssetListController.
 */
class CompanyListController extends Controller
{
    public function index()
    {
        return view('tenant.companies.list');
    }
}
