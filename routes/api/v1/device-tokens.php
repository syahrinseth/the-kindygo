<?php

use App\Http\Controllers\API\V1\DeviceTokenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Device Token Routes
|--------------------------------------------------------------------------
|
| These routes handle device token management for mobile apps including
| registering devices for push notifications and managing device tokens.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [DeviceTokenController::class, 'index'])->name('api.v1.device-tokens.index');
    Route::post('/', [DeviceTokenController::class, 'store'])->name('api.v1.device-tokens.store');
    Route::delete('/{deviceToken}', [DeviceTokenController::class, 'destroy'])->name('api.v1.device-tokens.destroy');
});
