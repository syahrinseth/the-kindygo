<?php

use App\Http\Controllers\EInvoiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Legacy route - kept for backward compatibility
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// E-Invoice routes (existing)
Route::middleware('auth:sanctum')->prefix('einvoice')->group(function () {
    Route::post('/invoices/{invoice}/submit', [EInvoiceController::class, 'submitInvoice']);
    Route::get('/invoices/{invoice}/status', [EInvoiceController::class, 'getStatus']);
    Route::post('/invoices/{invoice}/cancel', [EInvoiceController::class, 'cancelInvoice']);
    Route::get('/invoices/{invoice}/validation-url', [EInvoiceController::class, 'getValidationUrl']);
    Route::get('/invoices/{invoice}/preview', [EInvoiceController::class, 'previewInvoiceData']);
});

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
|
| Mobile API routes with versioning. All routes are prefixed with /api/v1
|
*/
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(base_path('routes/api/v1/auth.php'));
    Route::prefix('profile')->group(base_path('routes/api/v1/profile.php'));
    Route::prefix('children')->group(base_path('routes/api/v1/children.php'));
    Route::prefix('invoices')->group(base_path('routes/api/v1/invoices.php'));
    Route::prefix('payments')->group(base_path('routes/api/v1/payments.php'));
    Route::prefix('notifications')->group(base_path('routes/api/v1/notifications.php'));
    Route::prefix('device-tokens')->group(base_path('routes/api/v1/device-tokens.php'));
    Route::prefix('tenants')->group(base_path('routes/api/v1/tenants.php'));
});
