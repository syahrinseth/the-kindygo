<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\TenantInvitationController;
use App\Http\Controllers\SecureMediaController;

Route::get('/', function () {
    return view('welcome');
});

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Tenant Invitation Routes
Route::get('/invitations/{token}', [TenantInvitationController::class, 'accept'])
    ->name('tenant-invitations.accept');

// Secure Media Routes
Route::middleware('auth')->group(function () {
    Route::get('/media/{media}', [SecureMediaController::class, 'show'])->name('media.show');
    Route::get('/media/{media}/download', [SecureMediaController::class, 'download'])->name('media.download');
    Route::get('/media/{media}/conversions/{conversion}', [SecureMediaController::class, 'conversion'])->name('media.conversion');
});
