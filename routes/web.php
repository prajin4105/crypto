<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WalletController;
// Auth routes - these need to be in web.php for session handling
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::post('/register', [AuthController::class, 'register']);

// User endpoint - needs auth middleware
Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'user']);

Route::get('/', fn () => view('welcome'));
Route::get('/{any}', fn () => view('welcome'))->where('any', '.*');

// Wallet routes moved to api.php for consistency
