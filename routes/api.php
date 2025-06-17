<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EInvoiceController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// E-Invoice routes
Route::middleware('auth:sanctum')->prefix('einvoice')->group(function () {
    Route::post('/invoices/{invoice}/submit', [EInvoiceController::class, 'submitInvoice']);
    Route::get('/invoices/{invoice}/status', [EInvoiceController::class, 'getStatus']);
    Route::post('/invoices/{invoice}/cancel', [EInvoiceController::class, 'cancelInvoice']);
    Route::get('/invoices/{invoice}/validation-url', [EInvoiceController::class, 'getValidationUrl']);
    Route::get('/invoices/{invoice}/preview', [EInvoiceController::class, 'previewInvoiceData']);
});
