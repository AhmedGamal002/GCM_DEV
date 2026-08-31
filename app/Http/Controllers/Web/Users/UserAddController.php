<?php

namespace App\Http\Controllers\Web\Users;

use App\Http\Controllers\Controller;

/**
 * Blade shell only — a dedicated page (not the offcanvas used by the
 * list page) per the FRD: "Multi Column with Form Separator" layout,
 * same pattern as /form/layouts-vertical. Actual creation happens via
 * POST /api/v1/users (UserController@store).
 */
class UserAddController extends Controller
{
    public function index()
    {
        return view('tenant.users.add');
    }
}
