<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;

// TEST
Route::get('/test', function () {
    return response()->json([
        'message' => 'API Product Service ShopEase berjalan',
        'service' => 'ProductService',
        'port'    => '8002'
    ]);
});

// =============================================
// PROVIDER: Menyediakan data produk untuk service lain
// =============================================
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::put('/products/{id}', [ProductController::class, 'update']);
Route::delete('/products/{id}', [ProductController::class, 'destroy']);

// PROVIDER: Dipanggil OrderService untuk kurangi stok
Route::post('/products/{id}/reduce-stock', [ProductController::class, 'reduceStock']);

// =============================================
// CONSUMER: Memanggil UserService
// =============================================

// Saat buat produk → validasi user_id ke UserService
Route::post('/products', [ProductController::class, 'store']);

// Ambil produk + data seller dari UserService
Route::get('/products/{id}/with-seller', [ProductController::class, 'showWithSeller']);
