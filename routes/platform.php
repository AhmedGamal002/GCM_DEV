<?php

use App\Http\Controllers\Platform\AuthenticatedSessionController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\PermissionController;
use App\Http\Controllers\Platform\RoleController;
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

// Role/Permission CRUD — Super Admin only, see ARCHITECTURE.md §3.5.
// Tenant users (any role, including system_admin) never reach these:
// `auth:platform` rejects a `web`-guard session outright.
Route::middleware('auth:platform')->prefix('platform')->name('platform.')->group(function () {
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

    Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store');
    Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');
});
