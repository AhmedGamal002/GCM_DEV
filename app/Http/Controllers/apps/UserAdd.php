<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;

/**
 * Blade shell only — a dedicated page (not the offcanvas used by the
 * list page) per the FRD: "Multi Column with Form Separator" layout,
 * same pattern as /form/layouts-vertical. Actual creation happens via
 * POST /api/v1/users (UserController@store).
 */
class UserAdd extends Controller
{
    public function index()
    {
        return view('content.apps.app-user-add');
    }
}
