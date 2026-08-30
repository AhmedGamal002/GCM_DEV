<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\NewPasswordController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\DriverController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UserController;
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

        // /drivers/export MUST be registered before /drivers/{driver} —
        // same reasoning as /users/export (see above).
        Route::get('/drivers/export', [DriverController::class, 'export']);
        Route::get('/drivers', [DriverController::class, 'index']);
        Route::post('/drivers', [DriverController::class, 'store']);
        Route::get('/drivers/{driver}', [DriverController::class, 'show']);
        Route::patch('/drivers/{driver}', [DriverController::class, 'update']);
        Route::post('/drivers/{driver}/entry-permits', [DriverController::class, 'addEntryPermit']);
        Route::get('/drivers/{driver}/documents/{type}', [DriverController::class, 'downloadDocument']);
        Route::get('/driver-entry-permits/{permit}/attachment', [DriverController::class, 'downloadEntryPermitAttachment']);
    });
});
