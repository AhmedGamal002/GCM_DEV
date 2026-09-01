<?php

use App\Http\Controllers\Web\Vehicles\VehicleAccountController;
use App\Http\Controllers\Web\Vehicles\VehicleAddController;
use App\Http\Controllers\Web\Vehicles\VehicleListController;
use App\Http\Controllers\Web\AuthenticatedSessionController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\Drivers\DriverAccountController;
use App\Http\Controllers\Web\NewPasswordController;
use App\Http\Controllers\Web\PasswordResetLinkController;
use App\Http\Controllers\Web\Profile\AccountSettingsController;
use App\Http\Controllers\Web\Profile\SecuritySettingsController;
use App\Http\Controllers\Web\Users\UserAccountController;
use App\Http\Controllers\Web\Users\UserAddController;
use App\Http\Controllers\Web\Users\UserListController;
use Illuminate\Support\Facades\Route;

/**
 * GCM Portal's own real tenant-side page routes — split out of web.php so
 * that file can stay the untouched Vuexy template scaffold.
 * `require`d from routes/web.php, so it inherits the same 'web' middleware
 * group automatically — no separate registration needed.
 *
 * Route paths and names are UNCHANGED from before this split (e.g.
 * '/app/vehicle/list' / 'app-vehicle-list') — only the controller classes
 * and Blade view locations moved. Nothing that keys off a route name
 * (menu JSON, breadcrumbs, JS route() calls, tests) needed to change.
 *
 * NOTE: this branch currently carries only the Vehicles module's routes.
 * The Users / Drivers / Auth / Profile / dashboard routes land here on
 * merge with the shared reorg branch — same file, additive.
 */
Route::middleware(['auth', 'tenant'])->group(function () {
    // Vehicles — Blade shells only; data via /api/v1/vehicles.
    // {vehicle} kept as a raw numeric id (see VehicleController's docblock).
    Route::get('/app/vehicle/list', [VehicleListController::class, 'index'])->name('app-vehicle-list');
    Route::get('/app/vehicle/add', [VehicleAddController::class, 'index'])->name('app-vehicle-add');
    Route::get('/app/vehicle/view/{vehicle}', [VehicleAccountController::class, 'view'])->whereNumber('vehicle')->name('app-vehicle-view');
    Route::get('/app/vehicle/edit/{vehicle}', [VehicleAccountController::class, 'edit'])->whereNumber('vehicle')->name('app-vehicle-edit');
});




/**
 * GCM Portal's own real tenant-side page routes — split out of web.php so
 * that file can stay the untouched Vuexy template scaffold (kept around
 * as a living UI-kit/pattern reference until each piece is harvested or
 * removed — see ARCHITECTURE.md's Postman/folder-structure notes).
 * `require`d from routes/web.php, so it inherits the same 'web'
 * middleware group automatically — no separate registration needed.
 *
 * Route paths and names are UNCHANGED from before this split (e.g.
 * '/app/user/list' / 'app-user-list') — only the controller classes and
 * Blade view locations moved. Nothing that keys off a route name (menu
 * JSON, breadcrumbs, JS route() calls, tests) needed to change.
 *
 * Login/logout/forgot-password/reset-password themselves are handled
 * entirely by Api\V1\Auth\* (see routes/api.php) — the routes below only
 * render the Blade shell; see AuthenticatedSessionController's docblock.
 */
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'tenant'])
    ->name('dashboard');

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/app/user/list', [UserListController::class, 'index'])->name('app-user-list');
    Route::get('/app/user/add', [UserAddController::class, 'index'])->name('app-user-add');
    Route::get('/app/user/view/{user}', [UserAccountController::class, 'view'])->whereNumber('user')->name('app-user-view');
    Route::get('/app/user/edit/{user}', [UserAccountController::class, 'edit'])->whereNumber('user')->name('app-user-edit');

    Route::get('/app/driver/list', [DriverAccountController::class, 'list'])->name('app-driver-list');
    Route::get('/app/driver/add', [DriverAccountController::class, 'add'])->name('app-driver-add');
    Route::get('/app/driver/view/{driver}', [DriverAccountController::class, 'view'])->whereNumber('driver')->name('app-driver-view');
    Route::get('/app/driver/edit/{driver}', [DriverAccountController::class, 'edit'])->whereNumber('driver')->name('app-driver-edit');

    Route::get('/pages/account-settings-account', [AccountSettingsController::class, 'index'])->name('pages-account-settings-account');
    Route::get('/pages/account-settings-security', [SecuritySettingsController::class, 'index'])->name('pages-account-settings-security');
});
