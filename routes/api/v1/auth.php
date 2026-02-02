<?php

use App\Http\Controllers\API\V1\AuthController;
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

// Protected routes (authentication required)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user'])->name('api.v1.auth.user');
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
    Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('api.v1.auth.logout-all');
});
