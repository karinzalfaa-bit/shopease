<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;

Route::apiResource('orders', OrderController::class)->except(['update', 'destroy']);
Route::patch('orders/{id}/status', [OrderController::class, 'updateStatus']);
