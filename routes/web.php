<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\Payment\ChipPaymentController;
use App\Http\Controllers\Payment\PaymentReceiptController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuotationPdfController;
use App\Http\Controllers\SecureMediaController;
use App\Http\Controllers\TenantDirectoryController;
use App\Http\Controllers\TenantInvitationController;
use App\Livewire\TenantRegistrationWizard;
use Illuminate\Support\Facades\Route;

// Authenticated users: redirect based on role and profile completion
Route::middleware('auth')->get('/', function () {
    $user = auth()->user();

    // If profile is not completed, redirect to complete it
    if (! $user->profile_completed) {
        // If user has a current tenant, redirect to tenant registration wizard
        if ($user->current_tenant_id) {
            $tenant = $user->currentTenant();
            if ($tenant) {
                return redirect()->route('tenant.register.form', [
                    'tenant' => $tenant->slug,
                    'step' => $user->getCurrentRegistrationStep(),
                    'email' => $user->email,
                ]);
            }
        }

        // Fallback to profile completion page if no tenant
        return redirect()->route('profile.complete')
            ->with('warning', 'Please complete your profile to continue.');
    }

    // Profile is completed - route to appropriate dashboard
    // Admin roles (Super Admin, Admin, Principal, Teacher) redirect to /admin panel
    if ($user->isAdmin()) {
        return redirect('/admin');
    }

    // Parents and other users stay on root panel (dashboard)
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
Route::prefix('payments/chip')->name('payments.chip.')->group(function () {
    Route::get('success/{payment}', [ChipPaymentController::class, 'success'])
        ->middleware('signed')
        ->name('success');
    Route::get('failure/{payment}', [ChipPaymentController::class, 'failure'])
        ->middleware('signed')
        ->name('failure');
    Route::get('cancel/{payment}', [ChipPaymentController::class, 'cancel'])
        ->middleware('signed')
        ->name('cancel');
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
Route::middleware('auth')->prefix('payments')->name('payments.receipt.')->group(function () {
    Route::get('{payment}/download-receipt', [PaymentReceiptController::class, 'downloadReceipt'])
        ->name('download');
    Route::get('{payment}/stream-receipt', [PaymentReceiptController::class, 'streamReceipt'])
        ->name('stream');
});
