<?php

namespace App\Http\Controllers\Web\Companies;

use App\Http\Controllers\Controller;

/**
 * Blade shell only — the create form posts to /api/v1/companies.
 */
class CompanyAddController extends Controller
{
    public function index()
    {
        return view('tenant.companies.add');
    }
}
