<?php

use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('/me', [MeController::class, 'show']);

    // Only `show` this week — see UserController's docblock. Full CRUD
    // (index/store/update/destroy) lands in Week 2.
    Route::get('/users/{user}', [UserController::class, 'show']);
});
