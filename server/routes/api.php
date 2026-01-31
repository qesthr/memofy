<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MemoController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/


// Public Authentication (with rate limiting)
Route::middleware('throttle:login')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/google-token', [AuthController::class, 'googleTokenLogin']);
});

Route::post('/verify-recaptcha', [AuthController::class, 'verifyRecaptcha']);
Route::middleware('throttle:api')->get('/check-auth', [AuthController::class, 'checkAuth']);

// User Invitation & Setup
Route::get('/auth/verify-token/{token}', [AuthController::class, 'verifyInvitationToken']); // Legacy support for token URL
Route::post('/auth/setup-password', [AuthController::class, 'setupPassword']);

// Google Socialite (Redirect Flow)
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

// Google Calendar Integration
Route::middleware('auth:sanctum')->prefix('calendar')->group(function () {
    Route::post('/connect', [App\Http\Controllers\Api\GoogleCalendarController::class, 'connect']);
    Route::post('/disconnect', [App\Http\Controllers\Api\GoogleCalendarController::class, 'disconnect']);
    
    // Unified Calendar Events
    Route::get('/events', [App\Http\Controllers\Api\CalendarController::class, 'index']);
    Route::post('/events', [App\Http\Controllers\Api\CalendarController::class, 'store']);
    Route::put('/events/{event}', [App\Http\Controllers\Api\CalendarController::class, 'update']);
    Route::delete('/events/{event}', [App\Http\Controllers\Api\CalendarController::class, 'destroy']);
    Route::post('/events/{event}/respond', [App\Http\Controllers\Api\CalendarController::class, 'respond']);
});

// OAuth Callback (must be public as Google redirects here without auth headers)
Route::get('/calendar/auth/callback', [App\Http\Controllers\Api\GoogleCalendarController::class, 'callback']);

// Protected Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Auth Management
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/current-user', [AuthController::class, 'getCurrentUser']);
    Route::put('/me', [AuthController::class, 'updateMe']); // Ensure updateMe exists in AuthController or add generic one
    Route::post('/me/profile-picture', [AuthController::class, 'uploadMyProfilePicture']); // Ensure this exists

    // Dashboard (Higher rate limit)
    Route::middleware('throttle:dashboard')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
    });

    // Memos
    Route::apiResource('memos', MemoController::class);
    Route::post('/memos/{id}/rollback', [MemoController::class, 'rollback']);

    // Activity Logs
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/activity-logs/{id}', [ActivityLogController::class, 'show']);

    // User Management (Admin Only protected by policy or middleware inside controller, or here)
    Route::apiResource('users', UserController::class); // Index, show, store, update, destroy
    
    // User Invitation (Stricter rate limit to prevent spam)
    Route::middleware('throttle:invitations')->group(function () {
        Route::post('/users/invite', [UserController::class, 'inviteUser']);
    });
    
    Route::patch('/users/{id}/toggle-active', [UserController::class, 'toggleActive']);
    Route::post('/users/restore-all', [UserController::class, 'restoreAll']);
    
    // Legacy Admin Routes mappings (if needed for frontend compatibility)
    Route::prefix('admin')->group(function () {
        Route::middleware('throttle:dashboard')->group(function () {
            Route::get('/dashboard-stats', [DashboardController::class, 'index']); // Map to index
        });
        // .users, .activityLogs maps to standard resources above
    });
});
