<?php

namespace App\Http\Controllers\Web\Projects;

use App\Http\Controllers\Controller;

/**
 * Blade shells only, per the FRD's dedicated "project details" and "edit
 * project" pages. Both fetch/save against /api/v1/projects/{id}; the
 * {project} param isn't route-model-bound (same SubstituteBindings-before-
 * tenant reason as ProjectController), so authorization happens through
 * the API call, not here.
 */
class ProjectAccountController extends Controller
{
    public function view(int $project)
    {
        return view('tenant.projects.view', ['projectId' => $project]);
    }

    public function edit(int $project)
    {
        return view('tenant.projects.edit', ['projectId' => $project]);
    }
}
