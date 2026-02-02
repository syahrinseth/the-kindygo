<?php

use App\Http\Controllers\API\V1\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Profile Routes
|--------------------------------------------------------------------------
|
| These routes handle user profile management for mobile apps including
| viewing and updating profile information, and photo uploads.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [ProfileController::class, 'show'])->name('api.v1.profile.show');
    Route::put('/', [ProfileController::class, 'update'])->name('api.v1.profile.update');
    Route::post('/photo', [ProfileController::class, 'uploadPhoto'])->name('api.v1.profile.photo');
    Route::delete('/photo', [ProfileController::class, 'deletePhoto'])->name('api.v1.profile.photo.delete');
});
