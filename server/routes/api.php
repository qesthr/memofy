<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MemoController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\UserSignatureController;
use App\Http\Controllers\Api\MemoTemplateController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\LockController;
use App\Http\Controllers\Api\ArchiveController;
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
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
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
    Route::post('/events', [App\Http\Controllers\Api\CalendarController::class, 'store'])->middleware('can:calendar.add_event');
    Route::put('/events/{event}', [App\Http\Controllers\Api\CalendarController::class, 'update'])->middleware('can:calendar.edit_event');
    Route::delete('/events/{event}', [App\Http\Controllers\Api\CalendarController::class, 'destroy'])->middleware('can:calendar.archive_event');
    Route::post('/events/{event}/respond', [App\Http\Controllers\Api\CalendarController::class, 'respond']);
});

// OAuth Callback (must be public as Google redirects here without auth headers)
Route::get('/calendar/auth/callback', [App\Http\Controllers\Api\GoogleCalendarController::class, 'callback']);

// Protected Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Auth Management
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/current-user', [AuthController::class, 'getCurrentUser']);
    Route::put('/me', [AuthController::class, 'updateMe']);
    Route::put('/me/theme', [AuthController::class, 'updateTheme']);
    Route::post('/me/profile-picture', [AuthController::class, 'uploadMyProfilePicture']);

    // Dashboard (Higher rate limit)
    Route::middleware('throttle:dashboard')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
    });

    // Reports & Analytics
    Route::middleware('throttle:dashboard')->group(function () {
        Route::get('/reports', [ReportController::class, 'index']);
    });

    // Lock Management (Concurrency Control)
    Route::prefix('locks')->middleware('auth:sanctum')->group(function () {
        Route::post('/acquire', [LockController::class, 'acquire']);
        Route::post('/release', [LockController::class, 'release']);
        Route::post('/force-release', [LockController::class, 'forceRelease']);
        Route::get('/status', [LockController::class, 'status']);
        Route::post('/heartbeat', [LockController::class, 'heartbeat']);
        Route::get('/my-locks', [LockController::class, 'myLocks']);
        Route::get('/all-locks', [LockController::class, 'allLocks']);
        Route::get('/settings', [LockController::class, 'getSettings']);
        Route::put('/settings', [LockController::class, 'updateSettings']);
    });

    // Archive Management
    Route::prefix('archive')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [ArchiveController::class, 'index']);
        Route::post('/restore-all', [ArchiveController::class, 'restoreAll']);
        
        Route::prefix('users')->group(function () {
            Route::post('/restore/{id}', [ArchiveController::class, 'restoreUser']);
            Route::delete('/force-delete/{id}', [ArchiveController::class, 'forceDeleteUser']);
        });
        
        Route::prefix('memos')->group(function () {
            Route::post('/restore/{id}', [ArchiveController::class, 'restoreMemo']);
            Route::delete('/force-delete/{id}', [ArchiveController::class, 'forceDeleteMemo']);
        });
        
        Route::prefix('events')->group(function () {
            Route::post('/restore/{id}', [ArchiveController::class, 'restoreEvent']);
            Route::delete('/force-delete/{id}', [ArchiveController::class, 'forceDeleteEvent']);
        });
    });

    // Memos
    Route::get('/memos', [MemoController::class, 'index'])->middleware('can:memo.view');
    Route::post('/memos', [MemoController::class, 'store'])->middleware('can:memo.create');
    Route::get('/memos/{memo}', [MemoController::class, 'show'])->middleware('can:memo.view');
    Route::put('/memos/{memo}', [MemoController::class, 'update'])->middleware('can:memo.edit');
    Route::delete('/memos/{memo}', [MemoController::class, 'destroy'])->middleware('can:memo.archive');
    Route::post('/memos/{id}/rollback', [MemoController::class, 'rollback'])->middleware('can:memo.unarchive');
    Route::post('/memos/{id}/acknowledge', [MemoController::class, 'acknowledge'])->middleware('can:memo.view');

    // Activity Logs
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/activity-logs/{id}', [ActivityLogController::class, 'show']);

    // User Management
    Route::get('/users', [UserController::class, 'index'])->middleware('can:faculty.view');
    Route::post('/users', [UserController::class, 'store'])->middleware('can:faculty.add');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('can:faculty.view');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('can:faculty.edit');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('can:faculty.remove_permanently');
    
    // User Invitation (Stricter rate limit to prevent spam)
    Route::middleware('throttle:invitations')->group(function () {
        Route::post('/users/invite', [UserController::class, 'inviteUser'])->middleware('can:faculty.add');
    });
    
    Route::patch('/users/{id}/toggle-active', [UserController::class, 'toggleActive']); // Handled inside controller for granular archive/unarchive check
    Route::post('/users/restore-all', [UserController::class, 'restoreAll'])->middleware('can:faculty.unarchive');

    // Memo Customization
    Route::apiResource('departments', DepartmentController::class);
    
    Route::get('/signatures', [UserSignatureController::class, 'index']);
    Route::post('/signatures', [UserSignatureController::class, 'store']);
    Route::delete('/signatures/{userSignature}', [UserSignatureController::class, 'destroy']);
    Route::post('/signatures/{userSignature}/default', [UserSignatureController::class, 'setDefault']);
    
    Route::apiResource('memo-templates', MemoTemplateController::class);
    
    // Roles & Permissions
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/roles/{id}', [RoleController::class, 'show']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::put('/roles/{id}', [RoleController::class, 'update']);
    Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
    Route::get('/roles/{id}/permissions', [RoleController::class, 'getRolePermissions']);
    Route::put('/roles/{id}/permissions', [RoleController::class, 'updatePermissions']);
    Route::post('/roles/assign', [RoleController::class, 'assignRole']);
    Route::get('/permissions', [RoleController::class, 'permissions']);
    Route::put('/roles/{id}/permissions', [RoleController::class, 'updatePermissions']);
    
    // File Uploads
    Route::post('/upload', [FileController::class, 'upload']);
    Route::get('/download/{path}', [FileController::class, 'download'])->where('path', '.*');
    
    // Legacy Admin Routes mappings (if needed for frontend compatibility)
    Route::prefix('admin')->group(function () {
        Route::middleware('throttle:dashboard')->group(function () {
            Route::get('/dashboard-stats', [DashboardController::class, 'index']); // Map to index
        });
        // .users, .activityLogs maps to standard resources above
    });
});
