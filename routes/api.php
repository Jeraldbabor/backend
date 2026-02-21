<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Admin authentication routes for the CampusEye Gate System.
| Public routes: login
| Protected routes (require Sanctum token): logout, me
|
*/

// --- Admin Auth Routes ---
Route::prefix('admin')->group(function () {

    // Public: Admin login (no auth required)
    Route::post('/login', [AuthController::class, 'login']);

    // Protected: Requires a valid Sanctum token
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/password', [AuthController::class, 'updatePassword']);
        Route::delete('/account', [AuthController::class, 'deleteAccount']);

        // User Management CRUD
        Route::apiResource('/users', UserController::class);
    });
});
