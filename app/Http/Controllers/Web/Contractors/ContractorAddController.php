<?php

namespace App\Http\Controllers\Web\Contractors;

use App\Http\Controllers\Controller;

/**
 * Blade shell only — the create form posts to /api/v1/contractors.
 */
class ContractorAddController extends Controller
{
    public function index()
    {
        return view('tenant.contractors.add');
    }
}
