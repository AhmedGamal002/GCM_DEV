<?php

use App\Http\Middleware\EnsureTenant;
use App\Http\Middleware\LocaleMiddleware;
use App\Http\Middleware\RequiresOtp;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(__DIR__.'/../routes/platform.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Sanctum SPA cookie mode: treats requests from SANCTUM_STATEFUL_DOMAINS
        // as first-party (session-cookie) rather than requiring a bearer token.
        $middleware->statefulApi();
        // Only answer to APP_URL's host (and subdomains) plus TRUSTED_HOSTS — otherwise a forged Host header could make
        // the password-reset email point at an attacker's site. Skipped by Laravel for `local` and the test suite.
        $middleware->trustHosts(at: fn () => array_map(fn (string $host) => '^'.preg_quote($host, '/').'$', config('app.trusted_hosts')));
        $middleware->append(SecurityHeaders::class);
        // The panel's own API calls come from a logged-in user, so the ceiling is per user (per IP when a request has
        // no session); the 'api' limiter itself is in AppServiceProvider.
        $middleware->throttleApi();
        // Locale applies to Blade pages AND the JSON API the panel calls
        // (localised names like vehicle categories come back in the API
        // response) — the SPA-mode session cookie carries the choice to both.
        $middleware->web(LocaleMiddleware::class);
        $middleware->api(append: LocaleMiddleware::class);
        $middleware->alias([
            'tenant' => EnsureTenant::class,
            'otp' => RequiresOtp::class,
        ]);
        $middleware->redirectGuestsTo(function ($request) {
            return $request->is('platform/*') ? route('platform.login') : route('login');
        });
        $middleware->redirectUsersTo(function ($request) {
            return $request->is('platform/*') ? route('platform.dashboard') : route('dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
