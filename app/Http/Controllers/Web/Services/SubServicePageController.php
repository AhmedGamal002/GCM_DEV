<?php

namespace App\Http\Controllers\Web\Services;

use App\Http\Controllers\Controller;

/**
 * Blade shells only for the sub-services pages (FRD §1.9.2) — the
 * accordion list / create / view / edit. Data via /api/v1/sub-services.
 * Authorization happens through the API calls (see MainServicePageController).
 */
class SubServicePageController extends Controller
{
    public function list()
    {
        return view('tenant.sub-services.list');
    }

    public function add()
    {
        return view('tenant.sub-services.add');
    }

    public function view(int $service)
    {
        return view('tenant.sub-services.view', ['serviceId' => $service]);
    }

    public function edit(int $service)
    {
        return view('tenant.sub-services.edit', ['serviceId' => $service]);
    }
}
