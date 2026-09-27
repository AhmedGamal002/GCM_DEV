<?php

use App\Http\Controllers\Api\V1\AssetCapacityCategoryController;
use App\Http\Controllers\Api\V1\AssetController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\NewPasswordController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\DriverController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VehicleCategoryController;
use App\Http\Controllers\Api\V1\VehicleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Single login endpoint for the web panel (cookie) and mobile (token)
    // — see LoginController's docblock. No 'tenant' middleware: the
    // tenant isn't known until after credentials are verified.
    Route::post('/auth/login', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::post('/auth/logout', [LogoutController::class, 'destroy'])->middleware('auth:sanctum');
    Route::post('/auth/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:password-reset');
    Route::post('/auth/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:password-reset');

    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
        Route::get('/me', [MeController::class, 'show']);
        Route::patch('/me', [ProfileController::class, 'update']);
        Route::patch('/me/password', [ProfileController::class, 'updatePassword']);

        // Read-only reference data — every tenant role may read it (e.g.
        // for a role-assignment dropdown). No mutation route exists here
        // at all: Role/Permission CRUD lives entirely under
        // routes/platform.php, guard `platform` — see ARCHITECTURE.md §3.5.
        Route::get('/roles', [RoleController::class, 'index']);

        // /users/export MUST be registered before /users/{user} — {user}
        // is a raw int param (see UserController's docblock), so "export"
        // would otherwise be swallowed by {user} and 404 on findOrFail('export').
        Route::get('/users/export', [UserController::class, 'export']);
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
        Route::patch('/users/{user}/status', [UserController::class, 'status']);

        // Fleet — Vehicles (Week 3). Read-only reference lists first, then
        // the resource. /vehicles/export and /vehicles/stats MUST come
        // before /vehicles/{vehicle} — {vehicle} is a raw int param (see
        // VehicleController's docblock), so those literals would otherwise
        // be swallowed and 404 on findOrFail('export').
        Route::get('/vehicle-categories', [VehicleCategoryController::class, 'index']);
        Route::get('/vehicle-categories/export', [VehicleCategoryController::class, 'export']);
        Route::post('/vehicle-categories', [VehicleCategoryController::class, 'store']);
        Route::get('/vehicle-categories/{category}', [VehicleCategoryController::class, 'show'])->whereNumber('category');
        Route::patch('/vehicle-categories/{category}', [VehicleCategoryController::class, 'update'])->whereNumber('category');
        Route::delete('/vehicle-categories/{category}', [VehicleCategoryController::class, 'destroy'])->whereNumber('category');

        Route::get('/vehicles/export', [VehicleController::class, 'export']);
        Route::get('/vehicles/stats', [VehicleController::class, 'stats']);
        Route::get('/vehicles', [VehicleController::class, 'index']);
        Route::post('/vehicles', [VehicleController::class, 'store']);
        Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show']);
        Route::patch('/vehicles/{vehicle}', [VehicleController::class, 'update']);
        Route::patch('/vehicles/{vehicle}/status', [VehicleController::class, 'status']);
        Route::get('/vehicles/{vehicle}/documents/{document}/download', [VehicleController::class, 'downloadDocument'])
            ->name('api.vehicles.documents.download');

        // Assets & Supply Hub (Week 3). Capacity categories first (their
        // `index` is open reference data — the vehicle & asset form
        // dropdowns read it), then the assets resource. /assets/export and
        // /assets/stats (and .../asset-capacity-categories/export) MUST
        // come before the {id} routes — same raw-int-param reasoning as
        // /vehicles above.
        Route::get('/asset-capacity-categories', [AssetCapacityCategoryController::class, 'index']);
        Route::get('/asset-capacity-categories/export', [AssetCapacityCategoryController::class, 'export']);
        Route::post('/asset-capacity-categories', [AssetCapacityCategoryController::class, 'store']);
        Route::get('/asset-capacity-categories/{category}', [AssetCapacityCategoryController::class, 'show'])->whereNumber('category');
        Route::patch('/asset-capacity-categories/{category}', [AssetCapacityCategoryController::class, 'update'])->whereNumber('category');

        Route::get('/assets/export', [AssetController::class, 'export']);
        Route::get('/assets/stats', [AssetController::class, 'stats']);
        Route::get('/assets', [AssetController::class, 'index']);
        Route::post('/assets', [AssetController::class, 'store']);
        Route::get('/assets/{asset}', [AssetController::class, 'show'])->whereNumber('asset');
        Route::patch('/assets/{asset}', [AssetController::class, 'update'])->whereNumber('asset');
        Route::patch('/assets/{asset}/status', [AssetController::class, 'status'])->whereNumber('asset');

        // /drivers/export and /drivers/stats MUST be registered before
        // /drivers/{driver} — same reasoning as /users/export (see above).
        Route::get('/drivers/export', [DriverController::class, 'export']);
        Route::get('/drivers/stats', [DriverController::class, 'stats']);
        Route::get('/drivers', [DriverController::class, 'index']);
        Route::post('/drivers', [DriverController::class, 'store']);
        Route::get('/drivers/{driver}', [DriverController::class, 'show']);
        Route::patch('/drivers/{driver}', [DriverController::class, 'update']);
        Route::post('/drivers/{driver}/entry-permits', [DriverController::class, 'addEntryPermit']);
        Route::get('/drivers/{driver}/documents/{type}', [DriverController::class, 'downloadDocument']);
        Route::get('/driver-entry-permits/{permit}/attachment', [DriverController::class, 'downloadEntryPermitAttachment']);
    });
});
