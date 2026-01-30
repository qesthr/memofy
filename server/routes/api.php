<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
// Google Auth
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Admin Routes
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard-stats', [\App\Http\Controllers\Api\AdminController::class, 'dashboardStats']);
        Route::get('/users', [\App\Http\Controllers\Api\AdminController::class, 'users']);
        Route::get('/activity-logs', [\App\Http\Controllers\Api\AdminController::class, 'activityLogs']);
        Route::get('/calendar-events', [\App\Http\Controllers\Api\AdminController::class, 'calendarEvents']);
    });
});

