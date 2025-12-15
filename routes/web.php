<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ChipPaymentController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SecureMediaController;
use App\Http\Controllers\TenantDirectoryController;
use App\Http\Controllers\TenantInvitationController;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect(Filament::getHomeUrl());
    }

    return redirect('/login');
});

// Public tenant directory
Route::get('/join', [TenantDirectoryController::class, 'index'])->name('tenant.directory');

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    // Temporarily redirect register route to login
    Route::get('/register', function () {
        return redirect('/login');
    })->name('register');
    Route::post('/register', [RegisterController::class, 'register']);

    // Tenant-specific registration routes
    Route::get('/register/{tenantSlug}', [RegisterController::class, 'showTenantRegistrationForm'])
        ->name('tenant.register.form');
    Route::post('/register/{tenantSlug}', [RegisterController::class, 'registerToTenant'])
        ->name('tenant.register');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Alias route for Filament logout links
Route::post('/filament/logout', [LoginController::class, 'logout'])->name('filament.app.auth.logout')->middleware('auth');

// Profile completion routes
Route::middleware('auth')->group(function () {
    Route::get('/profile/complete', [ProfileController::class, 'showCompleteForm'])->name('profile.complete');
    Route::post('/profile/complete', [ProfileController::class, 'complete']);
});

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
    Route::get('success/{payment}', [ChipPaymentController::class, 'success'])
        ->name('success');
    // ->middleware('signed'); // temp disable
    Route::get('failure/{payment}', [ChipPaymentController::class, 'failure'])
        ->name('failure');
    // ->middleware('signed'); // temp disable
    Route::get('cancel/{payment}', [ChipPaymentController::class, 'cancel'])
        ->name('cancel');
    // ->middleware('signed'); // temp disable
    Route::post('webhook', [ChipPaymentController::class, 'webhook'])->name('webhook');
});

// Invoice PDF Download Route
Route::middleware('auth')->group(function () {
    Route::get('/invoice/{invoice}/download-pdf', [InvoicePdfController::class, 'download'])
        ->name('invoice.download-pdf');
});

// Payment Receipt PDF Download Routes
Route::middleware('auth')->group(function () {
    Route::get('/payment/{payment}/download-receipt', [PaymentReceiptController::class, 'downloadReceipt'])
        ->name('payment.download-receipt');
    Route::get('/payment/{payment}/stream-receipt', [PaymentReceiptController::class, 'streamReceipt'])
        ->name('payment.stream-receipt');
});
