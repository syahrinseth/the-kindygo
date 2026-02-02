<?php

use App\Http\Controllers\API\V1\TenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Tenant Routes
|--------------------------------------------------------------------------
|
| These routes handle multi-tenancy operations for mobile apps including
| listing available tenants and switching between tenants.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [TenantController::class, 'index'])->name('api.v1.tenants.index');
    Route::post('/{tenant}/switch', [TenantController::class, 'switch'])->name('api.v1.tenants.switch');
});
