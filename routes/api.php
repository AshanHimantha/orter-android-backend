<?php

use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\GenderController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StockController;
use App\Models\Product;

Route::get('genders', [GenderController::class, 'index']);
Route::get('categories', [ProductCategoryController::class, 'index']);
Route::get('collections', [CollectionController::class, 'index']);
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);
Route::get('products/image/{filename}', [ProductController::class, 'getImage']);
Route::get('products/{product}/stock', [StockController::class, 'checkStock']);
Route::get('stocks', [StockController::class, 'index']);
Route::get('stock-list/{limit?}', [StockController::class, 'stockList']);
Route::get('stocks/{stock}', [StockController::class, 'show']);
// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::middleware(['firebase'])->group(function () {
    Route::post('verify', [UserController::class, 'verifyAndSyncUser']);
    Route::get('/orders', [UserController::class, 'orders']);
    Route::apiResource('carts', CartController::class);
    Route::get('user-cart', [CartController::class, 'getUserCart']);
});

Route::prefix('admin')->group(function () {

    Route::post('login', [AdminController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AdminController::class, 'logout']);
        Route::get('details', [AdminController::class, 'details']);
        Route::post('register', [AdminController::class, 'register']);
        Route::apiResource('categories', ProductCategoryController::class)->except(['index']);
        Route::apiResource('genders', GenderController::class)->except(['index']);
        Route::apiResource('collections', CollectionController::class)->except(['index']);
        Route::apiResource('products', ProductController::class)->except(['index']);
        Route::apiResource('stocks', StockController::class);
        Route::patch('stocks/{stock}/quantities', [StockController::class, 'updateQuantities']);
    });
});


