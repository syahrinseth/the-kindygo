<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ChipPaymentController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuotationPdfController;
use App\Http\Controllers\SecureMediaController;
use App\Http\Controllers\TenantDirectoryController;
use App\Http\Controllers\TenantInvitationController;
use App\Livewire\TenantRegistrationWizard;
use Illuminate\Support\Facades\Route;

// Authenticated users: redirect based on role
Route::middleware('auth')->get('/', function () {
    $user = auth()->user();

    // Admin roles redirect to /admin panel
    if ($user->isAdmin()) {
        return redirect('/admin');
    }

    // Parents stay on root panel (dashboard)
    return redirect('/dashboard');
});

// Guests: go to login
Route::middleware('guest')->get('/login-redirect', function () {
    return redirect('/login');
});

// Public tenant directory
Route::get('/join', [TenantDirectoryController::class, 'index'])->name('tenant.directory');

// Public pages
Route::get('/terms-and-conditions', function () {
    return view('pages.terms-and-conditions');
})->name('terms');

Route::get('/letter-of-undertaking', function () {
    return view('pages.letter-of-undertaking');
})->name('undertaking');

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    // Temporarily redirect general register route to login
    Route::get('/register', function () {
        return redirect('/login');
    })->name('register');
});

// Tenant-specific registration routes with wizard (outside guest middleware)
Route::get('/register/{tenant:slug}', TenantRegistrationWizard::class)
    ->middleware('allow.incomplete.registration')
    ->name('tenant.register.form');

// Logout routes for different contexts
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');
Route::post('/filament/logout', [LoginController::class, 'logout'])->name('filament.app.auth.logout')->middleware('auth');
Route::post('/admin/logout', [LoginController::class, 'logout'])->name('filament.admin.auth.logout')->middleware('auth');

// Parent panel logout - use a closure to redirect to the main logout route
Route::match(['get', 'post'], '/parent/logout', function () {
    return app(\App\Http\Controllers\Auth\LoginController::class)->logout(request());
})->name('filament.parent.auth.logout')->middleware('auth');

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

// Quotation PDF Download Route
Route::middleware('auth')->group(function () {
    Route::get('/quotation/{quotation}/download-pdf', [QuotationPdfController::class, 'download'])
        ->name('quotation.download-pdf');
});

// Payment Receipt PDF Download Routes
Route::middleware('auth')->group(function () {
    Route::get('/payment/{payment}/download-receipt', [PaymentReceiptController::class, 'downloadReceipt'])
        ->name('payment.download-receipt');
    Route::get('/payment/{payment}/stream-receipt', [PaymentReceiptController::class, 'streamReceipt'])
        ->name('payment.stream-receipt');
});
