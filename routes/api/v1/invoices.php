<?php

use App\Http\Controllers\API\V1\InvoiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Invoice Routes
|--------------------------------------------------------------------------
|
| These routes handle invoice data access for mobile apps including
| listing invoices and viewing invoice details.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [InvoiceController::class, 'index'])->name('api.v1.invoices.index');
    Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('api.v1.invoices.show');
    Route::get('/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('api.v1.invoices.pdf');
});
