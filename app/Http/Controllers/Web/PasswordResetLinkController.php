<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

/**
 * Blade shell only — the actual send happens via
 * Api\V1\Auth\PasswordResetLinkController (POST /api/v1/auth/forgot-password),
 * same reasoning as AuthenticatedSessionController.
 */
class PasswordResetLinkController extends Controller
{
    public function create()
    {
        return view('content.authentications.auth-forgot-password-basic', [
            'pageConfigs' => ['myLayout' => 'blank'],
        ]);
    }
}
