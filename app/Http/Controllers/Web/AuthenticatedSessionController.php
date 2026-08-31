<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

/**
 * Blade shell only. Login/logout themselves are handled entirely by
 * Api\V1\Auth\LoginController/LogoutController — the login page submits
 * via JS to /api/v1/auth/login, and the navbar's logout button posts to
 * /api/v1/auth/logout. See LoginController's docblock for why this went
 * through the API from day one instead of a classic form POST: the same
 * endpoint will serve the driver mobile app later without any rework.
 */
class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        return view('tenant.auth.login', [
            'pageConfigs' => ['myLayout' => 'blank'],
        ]);
    }
}
