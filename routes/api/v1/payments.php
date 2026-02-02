<?php

use App\Http\Controllers\API\V1\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Payment Routes
|--------------------------------------------------------------------------
|
| These routes handle payment operations for mobile apps including
| creating payments, viewing payment history, and confirming payments.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [PaymentController::class, 'index'])->name('api.v1.payments.index');
    Route::post('/', [PaymentController::class, 'store'])->name('api.v1.payments.store');
    Route::get('/{payment}', [PaymentController::class, 'show'])->name('api.v1.payments.show');
    Route::post('/{payment}/confirm', [PaymentController::class, 'confirm'])->name('api.v1.payments.confirm');
});
