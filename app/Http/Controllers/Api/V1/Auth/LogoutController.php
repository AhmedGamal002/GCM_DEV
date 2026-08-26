<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\TransientToken;

/**
 * Mirrors LoginController's dual-mode design: a stateful (cookie) session
 * is represented by a TransientToken (Sanctum's marker for "this user came
 * from the web guard, not a real token row") and gets invalidated as a
 * session; a real mobile token gets revoked instead.
 */
class LogoutController extends Controller
{
    public function destroy(Request $request): Response
    {
        if ($request->user()->currentAccessToken() instanceof TransientToken) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } else {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->noContent();
    }
}
