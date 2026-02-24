<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::get('/pos', [PosController::class, 'index']);

Route::get('/', [ItemController::class, 'index']);
Route::post('/items', [ItemController::class, 'store']);
Route::put('/items/{item}', [ItemController::class, 'update']);
Route::delete('/items/{item}', [ItemController::class, 'destroy']);

Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);
Route::put('/products/{product}', [ProductController::class, 'update']);
Route::delete('/products/{product}', [ProductController::class, 'destroy']);
Route::post('/products/{product}/checkout', [CheckoutController::class, 'store']);

Route::get('/units', [UnitController::class, 'index']);
Route::post('/units', [UnitController::class, 'store']);
Route::delete('/units/{unit}', [UnitController::class, 'destroy']);
