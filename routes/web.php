<?php

use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemConsumptionController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RestockController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ItemController::class, 'index']);
Route::post('/items', [ItemController::class, 'store']);
Route::put('/items/{item}', [ItemController::class, 'update']);
Route::delete('/items/{item}', [ItemController::class, 'destroy']);
Route::post('/items/{item}/consume', [ItemConsumptionController::class, 'store']);

Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);
Route::put('/products/{product}', [ProductController::class, 'update']);
Route::delete('/products/{product}', [ProductController::class, 'destroy']);

Route::get('/produce', [ProductionController::class, 'index']);
Route::post('/produce', [ProductionController::class, 'store']);

Route::get('/restock', [RestockController::class, 'index']);
Route::post('/restock', [RestockController::class, 'store']);

Route::get('/units', [UnitController::class, 'index']);
Route::post('/units', [UnitController::class, 'store']);
Route::delete('/units/{unit}', [UnitController::class, 'destroy']);

