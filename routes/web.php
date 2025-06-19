<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\TenantInvitationController;
use App\Http\Controllers\SecureMediaController;
use App\Http\Controllers\ChipPaymentController;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect('/app');
    }
    return redirect('/login');
});

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    // Temporarily redirect register route to login
    Route::get('/register', function () {
        return redirect('/login');
    })->name('register');
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

// CHIP Payment Routes
Route::prefix('chip')->name('chip.')->group(function () {
    Route::get('success/{payment}', [ChipPaymentController::class, 'success'])->name('success');
    Route::get('failure/{payment}', [ChipPaymentController::class, 'failure'])->name('failure');
    Route::get('cancel/{payment}', [ChipPaymentController::class, 'cancel'])->name('cancel');
    Route::post('webhook', [ChipPaymentController::class, 'webhook'])->name('webhook');
});
