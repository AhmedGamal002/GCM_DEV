<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Blade shell only — the actual reset happens via
 * Api\V1\Auth\NewPasswordController (POST /api/v1/auth/reset-password),
 * same reasoning as AuthenticatedSessionController.
 */
class NewPasswordController extends Controller
{
    public function create(Request $request, string $token)
    {
        return view('content.authentications.auth-reset-password-basic', [
            'pageConfigs' => ['myLayout' => 'blank'],
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }
}
