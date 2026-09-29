<?php

namespace App\Http\Controllers\Web\Projects;

use App\Http\Controllers\Controller;

class ProjectAddController extends Controller
{
    public function index()
    {
        return view('tenant.projects.add');
    }
}
