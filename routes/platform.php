<?php

use App\Http\Controllers\Platform\AuthenticatedSessionController;
use App\Http\Controllers\Platform\DashboardController;
use Illuminate\Support\Facades\Route;

// Super Admin — entirely separate guard/provider/session from tenant users.
// Registered under the `web` middleware group via bootstrap/app.php's
// `then:` closure (session + CSRF, same as routes/web.php).
Route::middleware('guest:platform')->group(function () {
    Route::get('/platform/login', [AuthenticatedSessionController::class, 'create'])->name('platform.login');
    Route::post('/platform/login', [AuthenticatedSessionController::class, 'store']);
});

Route::post('/platform/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth:platform')
    ->name('platform.logout');

Route::get('/platform/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth:platform')
    ->name('platform.dashboard');
