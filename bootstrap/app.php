<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\LocaleMiddleware;
use App\Http\Middleware\EnsureTenant;

return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    web: __DIR__ . '/../routes/web.php',
    api: __DIR__ . '/../routes/api.php',
    commands: __DIR__ . '/../routes/console.php',
    health: '/up',
    then: function () {
      Route::middleware('web')->group(__DIR__ . '/../routes/platform.php');
    },
  )
  ->withMiddleware(function (Middleware $middleware) {
    // Sanctum SPA cookie mode: treats requests from SANCTUM_STATEFUL_DOMAINS
    // as first-party (session-cookie) rather than requiring a bearer token.
    $middleware->statefulApi();
    $middleware->web(LocaleMiddleware::class);
    $middleware->alias([
      'tenant' => EnsureTenant::class,
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
