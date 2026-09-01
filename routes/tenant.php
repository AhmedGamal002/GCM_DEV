<?php

use App\Http\Controllers\Web\Vehicles\VehicleAccountController;
use App\Http\Controllers\Web\Vehicles\VehicleAddController;
use App\Http\Controllers\Web\Vehicles\VehicleListController;
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
