<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\CartController;
use  App\Http\Controllers\NotificationSellerController;



Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes (perlu autentikasi)
Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::get('/user', [AuthController::class, 'getUser']);
    Route::post('/logout', [AuthController::class, 'logout']);


    Route::prefix('category')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::get('/{id}', [CategoryController::class, 'show']);
        Route::put('/{id}', [CategoryController::class, 'update']);
        Route::delete('/{id}', [CategoryController::class, 'destroy']);
        Route::get('/tree', [CategoryController::class, 'tree']); // Untuk mendapatkan struktur bertingkat
    });


    Route::prefix('products')  -> group(function()  {
        Route::post('/',[ProductsController::class,'createProduct']);
        Route::get('/',[ProductsController::class,'index']);
        Route::get('/{id}',[ProductsController::class,'show']);
        Route::put("/{id}",[ProductsController::class,'updateProduct']);
        Route::post('/{id}/variants',[ProductsController::class,'createVariantsAndInventory']);
        Route::put('/variants/{id}',[ProductsController::class,'updateVariantsAndInventory']);
        Route::delete('/{id}',[ProductsController::class,'deleteProduct']);
        Route::delete('/variants/{id}',[ProductsController::class,'deleteVariant']);
    });
    
    Route::prefix('order')  ->  group(function()  {
        Route::delete('/clear',[CartController::class,'clearCart']);
        Route::delete('/items/{itemId}',[CartController::class,'removeOrderItem']);
        Route::patch('/items/{itemId}/quantity', [CartController::class, 'updateOrderItemQuantity']);
        Route::get('/',[CartController::class,'getOrCreateCart']);
        Route::post('/checkout',[CartController::class,'checkout']);
        Route::post('/',[CartController::class,'addToCart']);
        Route::get('/orders', [CartController::class, 'getUserOrders']);
    });

    Route::prefix('notifications')  ->   group(function()  {
        Route::get('/', [NotificationSellerController::class, 'index']);
        Route::patch('/{id}/read', [NotificationSellerController::class, 'markAsRead']);
        Route::delete('/{id}', [NotificationSellerController::class, 'destroy']);
         Route::get('/user', [NotificationSellerController::class, 'userIndex']);
        Route::patch('/{id}/read/user', [NotificationSellerController::class, 'markAsReadUser']);
        Route::delete('/{id}/user', [NotificationSellerController::class, 'destroyUser']);
    });
    // Regular user routes
    Route::middleware(['user'])->prefix('user')->group(function () {
      
    });
   
});