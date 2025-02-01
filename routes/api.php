<?php

use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['firebase'])->group(function () {
    Route::get('/profile', [UserController::class, 'profile']);
    Route::get('/orders', [UserController::class, 'orders']);
});
