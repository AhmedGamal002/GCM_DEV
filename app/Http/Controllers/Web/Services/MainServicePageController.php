<?php

namespace App\Http\Controllers\Web\Services;

use App\Http\Controllers\Controller;

/**
 * Blade shells only for the main services pages (FRD §1.9.1) — list /
 * create / view / edit. Data via /api/v1/main-services. The {service}
 * param isn't route-model-bound (same SubstituteBindings-before-tenant
 * reason as the API controller), so authorization happens through the API
 * call, not here.
 */
class MainServicePageController extends Controller
{
    public function list()
    {
        return view('tenant.main-services.list');
    }

    public function add()
    {
        return view('tenant.main-services.add');
    }

    public function view(int $service)
    {
        return view('tenant.main-services.view', ['serviceId' => $service]);
    }

    public function edit(int $service)
    {
        return view('tenant.main-services.edit', ['serviceId' => $service]);
    }
}
