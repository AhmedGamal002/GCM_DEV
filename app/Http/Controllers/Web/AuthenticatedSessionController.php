<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Classic session-based login/logout for tenant users on the `web` guard.
 * A deliberate, narrow exception to "web.php = shell only": this
 * controller mutates the session rather than serving business data, and
 * Vuexy here is a traditional multi-page Blade app, not an SPA — no
 * benefit to bootstrapping an AJAX/CSRF-cookie flow for the single most
 * safety-critical request in the app.
 */
class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        return view('content.authentications.auth-login-basic', [
            'pageConfigs' => ['myLayout' => 'blank'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // The 'users' auth provider (TenantUnawareEloquentUserProvider) is
        // deliberately unscoped for this lookup — see its docblock: the
        // tenant isn't known until AFTER we know who the user is.
        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (Auth::guard('web')->user()->status === 'deactivated') {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
