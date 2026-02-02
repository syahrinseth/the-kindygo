<?php

use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\RegistrationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Authentication Routes
|--------------------------------------------------------------------------
|
| These routes handle user authentication for mobile apps including
| login, logout, registration, and email verification.
|
*/

// Public routes (no authentication required)
Route::post('/login', [AuthController::class, 'login'])->name('api.v1.auth.login');
Route::post('/register', [AuthController::class, 'register'])->name('api.v1.auth.register');
Route::post('/verify-email', [AuthController::class, 'verifyEmail'])->name('api.v1.auth.verify-email');
Route::post('/resend-verification', [AuthController::class, 'resendVerification'])->name('api.v1.auth.resend-verification');

// Multi-step registration routes (public)
Route::prefix('register')->name('api.v1.register.')->group(function () {
    Route::post('/step-1', [RegistrationController::class, 'step1'])->name('step1');
    Route::post('/verify-email', [RegistrationController::class, 'verifyEmail'])->name('verify-email');
});

// Protected routes (authentication required)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user'])->name('api.v1.auth.user');
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
    Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('api.v1.auth.logout-all');

    // Multi-step registration routes (protected - require authenticated user)
    Route::prefix('register')->name('api.v1.register.')->group(function () {
        Route::post('/step-2', [RegistrationController::class, 'step2'])->name('step2');
        Route::post('/step-3', [RegistrationController::class, 'step3'])->name('step3');
        Route::post('/step-4', [RegistrationController::class, 'step4'])->name('step4');
        Route::get('/status', [RegistrationController::class, 'status'])->name('status');
    });
});
