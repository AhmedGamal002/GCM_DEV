<?php

namespace App\Http\Controllers\Web\Companies;

use App\Http\Controllers\Controller;

/**
 * Blade shells only, per the FRD's dedicated "company details" and "edit
 * company" pages. Both fetch/save against /api/v1/companies/{id}; the
 * {company} param isn't route-model-bound (same SubstituteBindings-before-
 * tenant reason as CompanyController), so authorization happens through
 * the API call, not here.
 */
class CompanyAccountController extends Controller
{
    public function view(int $company)
    {
        return view('tenant.companies.view', ['companyId' => $company]);
    }

    public function edit(int $company)
    {
        return view('tenant.companies.edit', ['companyId' => $company]);
    }
}
