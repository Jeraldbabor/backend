<?php

use App\Http\Controllers\AttendanceLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| CampusEye Gate System API routes.
| - Kiosk routes: API-key authenticated for gate scanner devices
| - Admin routes: Sanctum token authenticated for admin users
| - Superadmin routes: Sanctum token authenticated for superadmin users
| - Notification routes: Sanctum token authenticated for all users
|
*/

// =======================================================================
// --- Kiosk Routes (API Key Auth — for gate scanner devices) ---
// =======================================================================
Route::prefix('kiosk')->middleware('kiosk')->group(function () {
    Route::post('/scan', [KioskController::class, 'scan']);
});

// =======================================================================
// --- Admin Auth & Management Routes ---
// =======================================================================
Route::prefix('admin')->group(function () {

    // Public: Admin login (no auth required)
    Route::post('/login', [AuthController::class, 'login']);

    // Protected: Requires a valid Sanctum token
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/password', [AuthController::class, 'updatePassword']);
        Route::delete('/account', [AuthController::class, 'deleteAccount']);

        // User Management CRUD (Admin scoped)
        Route::apiResource('/users', UserController::class);

        // Student Management CRUD (Admin scoped, includes RFID assignment)
        Route::apiResource('/students', StudentController::class);

        // Teacher Assignment CRUD (Admin scoped)
        Route::apiResource('/teachers', TeacherController::class);

        // Attendance Log Viewing (Admin scoped, read-only)
        Route::get('/attendance-logs/export', [AttendanceLogController::class, 'export']);
        Route::get('/attendance-logs', [AttendanceLogController::class, 'index']);
        Route::get('/attendance-logs/{attendanceLog}', [AttendanceLogController::class, 'show']);
    });
});

// =======================================================================
// --- Superadmin Routes ---
// =======================================================================
Route::prefix('superadmin')->group(function () {

    // Protected: Requires a valid Sanctum token
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // User Management CRUD (Global scoped)
        Route::apiResource('/users', \App\Http\Controllers\SuperAdminUserController::class);

        // School Management CRUD
        Route::apiResource('/schools', \App\Http\Controllers\SchoolController::class);
    });
});

// =======================================================================
// --- Notification Routes (for all authenticated users — mobile app) ---
// =======================================================================
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
});

// =======================================================================
// --- Mobile App Routes ---
// =======================================================================
Route::prefix('mobile')->group(function () {

    // Public: Mobile login (no auth required)
    Route::post('/login', [\App\Http\Controllers\MobileAuthController::class, 'login']);

    // Protected: Requires a valid Sanctum token
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [\App\Http\Controllers\MobileAuthController::class, 'logout']);
        Route::get('/me', [\App\Http\Controllers\MobileAuthController::class, 'me']);
        Route::get('/dashboard', [\App\Http\Controllers\MobileAuthController::class, 'dashboard']);
        Route::post('/push-token', [\App\Http\Controllers\MobileAuthController::class, 'storePushToken']);

        // Notifications (reuse existing controller)
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    });
});
