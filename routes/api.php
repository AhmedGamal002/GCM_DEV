<?php

use App\Http\Controllers\Api\V1\AssetCapacityCategoryController;
use App\Http\Controllers\Api\V1\AssetController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\NewPasswordController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\ClientUserController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\DriverController;
use App\Http\Controllers\Api\V1\FacilityController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProjectAssetController;
use App\Http\Controllers\Api\V1\ProjectController;
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
        Route::get('/users/{user}/images/{type}', [UserController::class, 'downloadImage'])
            ->whereNumber('user')
            ->name('api.users.images.download');

        // Client accounts (Part 6 of Phase 2, FRD V01.14 §1.4) — created and edited only here,
        // never through the generic /users form (see StoreClientUserRequest).
        Route::post('/client-users', [ClientUserController::class, 'store']);
        Route::patch('/client-users/{user}', [ClientUserController::class, 'update'])->whereNumber('user');

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

        // Intermediate waste facilities (FRD §1.8) — /facilities/export and
        // /facilities/stats MUST come before /facilities/{facility}, same
        // raw-int-param reasoning as /vehicles above.
        Route::get('/facilities/export', [FacilityController::class, 'export']);
        Route::get('/facilities/stats', [FacilityController::class, 'stats']);
        Route::get('/facilities', [FacilityController::class, 'index']);
        Route::post('/facilities', [FacilityController::class, 'store']);
        Route::get('/facilities/{facility}', [FacilityController::class, 'show'])->whereNumber('facility');
        Route::patch('/facilities/{facility}', [FacilityController::class, 'update'])->whereNumber('facility');
        Route::patch('/facilities/{facility}/status', [FacilityController::class, 'status'])->whereNumber('facility');
        Route::get('/facilities/{facility}/contract', [FacilityController::class, 'downloadContract'])->whereNumber('facility');

        // Client companies (Part 4 of Phase 2, FRD V01.14 §1.11). /companies/export
        // MUST come before /companies/{company} — same reasoning as /vehicles above.
        Route::get('/companies/export', [CompanyController::class, 'export']);
        Route::get('/companies', [CompanyController::class, 'index']);
        Route::post('/companies', [CompanyController::class, 'store']);
        Route::get('/companies/{company}', [CompanyController::class, 'show'])->whereNumber('company');
        Route::patch('/companies/{company}', [CompanyController::class, 'update'])->whereNumber('company');
        Route::patch('/companies/{company}/status', [CompanyController::class, 'status'])->whereNumber('company');
        Route::get('/companies/{company}/documents/{type}', [CompanyController::class, 'downloadDocument'])
            ->whereNumber('company')
            ->name('api.companies.documents.download');

        // Client projects (Part 5 of Phase 2, FRD V01.14 §1.12). /projects/export MUST come
        // before /projects/{project}. A project's assets are GET /assets?project_id=…;
        // inserting one is POST /projects/{project}/assets (FRD §1.7.3).
        Route::get('/projects/export', [ProjectController::class, 'export']);
        Route::get('/projects', [ProjectController::class, 'index']);
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->whereNumber('project');
        Route::patch('/projects/{project}', [ProjectController::class, 'update'])->whereNumber('project');
        Route::patch('/projects/{project}/status', [ProjectController::class, 'status'])->whereNumber('project');
        Route::post('/projects/{project}/assets', [ProjectAssetController::class, 'store'])->whereNumber('project');

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
