<?php

use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\GenderController;
use App\Http\Controllers\ProductCategoryController;

Route::get('genders', [GenderController::class, 'index']);
Route::get('categories', [ProductCategoryController::class, 'index']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['firebase'])->group(function () {
    Route::post('verify', [UserController::class, 'verifyAndSyncUser']);
    Route::get('/orders', [UserController::class, 'orders']);
});

Route::prefix('admin')->group(function () {

    Route::post('login', [AdminController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AdminController::class, 'logout']);
        Route::get('details', [AdminController::class, 'details']);
        Route::post('register', [AdminController::class, 'register']);
        Route::apiResource('categories', ProductCategoryController::class)->except(['index']);
        Route::apiResource('genders', GenderController::class)->except(['index']);
    });
});


