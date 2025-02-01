<?php

use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['firebase'])->group(function () {
    Route::post('verify', [UserController::class, 'verifyAndSyncUser']);
    Route::get('/orders', [UserController::class, 'orders']);
});
