<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CurrierController;
use App\Http\Controllers\GenderController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StockController;

// public routes
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
Route::get('stocks/category/{categoryId}/{limit?}', [StockController::class, 'filterByCategory']);
Route::get('latest-stocks/{limit?}', [StockController::class, 'getLatestStocks']);
Route::post('payhere/notify', [OrderController::class, 'updatePaymentStatus']);
Route::get('user-cart', [CartController::class, 'getUserCart']);
Route::get('order/{id}', [OrderController::class, 'getOrderById']);
Route::get('user/orders', [OrderController::class, 'getUserOrders']);
Route::post('/send-notification', [NotificationController::class, 'sendNotification']);


//firebase routes
Route::middleware(['firebase'])->group(function () {

    Route::post('verify', [UserController::class, 'verifyAndSyncUser']);
    Route::get('/orders', [UserController::class, 'orders']);
    Route::apiResource('carts', CartController::class);
    Route::patch('carts/{cart}/increase', [CartController::class, 'increaseQuantity']);
    Route::patch('carts/{cart}/decrease', [CartController::class, 'decreaseQuantity']);
    Route::post('/user/fcm-token', [UserController::class, 'updateFcmToken']);

});

//admin routes
Route::prefix('admin')->group(function () {
        
         //public login route
        Route::post('login', [AdminController::class, 'login']);
       
        //protected routes
        Route::middleware('auth:sanctum')->group(function () {
        Route::get('all-orders', [OrderController::class, 'getAllOrders']);
        Route::apiResource('orders', OrderController::class);
        Route::post('logout', [AdminController::class, 'logout']);
        Route::get('details', [AdminController::class, 'details']);
        Route::post('register', [AdminController::class, 'register']);
        Route::apiResource('categories', ProductCategoryController::class)->except(['index']);
        Route::apiResource('genders', GenderController::class)->except(['index']);
        Route::apiResource('collections', CollectionController::class)->except(['index']);
        Route::apiResource('products', ProductController::class)->except(['index']);
        Route::apiResource('stocks', StockController::class);
        Route::patch('stocks/{stock}/quantities', [StockController::class, 'updateQuantities']);
        Route::post('/orders/{id}/cancel', [OrderController::class, 'cancelOrder']);




        // Currier routes
    Route::prefix('curriers')->group(function () {
      Route::get('/', [CurrierController::class, 'index']);
      Route::post('/', [CurrierController::class, 'store']);
      Route::put('/{id}', [CurrierController::class, 'update']);
      Route::delete('/{id}', [CurrierController::class, 'destroy']);
      Route::patch('/{id}/toggle-active', [CurrierController::class, 'toggleActive']);
});
    });
});


