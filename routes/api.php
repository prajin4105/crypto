<?php

use App\Http\Controllers\Api\MarketController;
use App\Http\Controllers\Api\OrderbookController;
use App\Http\Controllers\Api\TradeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

// Public endpoints
Route::get('/markets', [MarketController::class, 'index']);
Route::get('/orderbook/{symbol}', [OrderbookController::class, 'show']);
Route::get('/trades/{symbol}', [TradeController::class, 'index']);

// Authenticated endpoints
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    
    Route::get('/wallets', [WalletController::class, 'index']);
    Route::post('/wallets/deposit', [WalletController::class, 'deposit']);
    Route::post('/wallets/withdraw', [WalletController::class, 'withdraw']);
    
    Route::get('/balance', [\App\Http\Controllers\Api\BalanceController::class, 'index']);
});
