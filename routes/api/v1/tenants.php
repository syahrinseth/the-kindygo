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
    Route::get('/current/chip-configuration', [TenantController::class, 'chipConfiguration'])->name('api.v1.tenants.chip-configuration.show');
    Route::put('/current/chip-configuration', [TenantController::class, 'updateChipConfiguration'])->name('api.v1.tenants.chip-configuration.update');
    Route::delete('/current/chip-configuration', [TenantController::class, 'destroyChipConfiguration'])->name('api.v1.tenants.chip-configuration.destroy');
    Route::post('/{tenant}/switch', [TenantController::class, 'switch'])->name('api.v1.tenants.switch');
});
