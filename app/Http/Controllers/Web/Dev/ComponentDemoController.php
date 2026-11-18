<?php

namespace App\Http\Controllers\Web\Dev;

use App\Http\Controllers\Controller;

/**
 * A small try-out page for the reusable browser components (live location, live camera, signature pad) that the
 * trip pages are built on. It exists only where SHOW_COMPONENT_DEMOS=true and never in production, and only the
 * system admin sees it (the route also carries the `can` middleware — this check is the second lock).
 */
class ComponentDemoController extends Controller
{
    public function index()
    {
        abort_unless(config('custom.custom.showComponentDemos') && ! app()->isProduction(), 404);

        return view('tenant.dev.components');
    }
}
