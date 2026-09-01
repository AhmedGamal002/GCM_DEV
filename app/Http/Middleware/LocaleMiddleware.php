<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocaleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Runs on both the `web` and `api` groups — `hasSession()` guards the
        // token-only (no session) case a future mobile client would use.
        if ($request->hasSession() && in_array($request->session()->get('locale'), ['en', 'ar'], true)) {
            app()->setLocale($request->session()->get('locale'));
        }

        return $next($request);
    }
}
