<?php

use App\Http\Controllers\API\V1\ChildController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Children Routes
|--------------------------------------------------------------------------
|
| These routes handle child data access for mobile apps including
| listing children and viewing individual child details.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [ChildController::class, 'index'])->name('api.v1.children.index');
    Route::get('/{child}', [ChildController::class, 'show'])->name('api.v1.children.show');
    Route::get('/{child}/photo', [ChildController::class, 'photo'])->name('api.v1.children.photo');
});
