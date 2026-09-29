<?php

namespace App\Http\Controllers\Web\Projects;

use App\Http\Controllers\Controller;

/**
 * Blade shell only — the list page fetches everything from
 * /api/v1/projects. Same pattern as Web\Companies\CompanyListController.
 */
class ProjectListController extends Controller
{
    public function index()
    {
        return view('tenant.projects.list');
    }
}
