<?php

use App\Http\Controllers\ItemController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ItemController::class, 'index']);
Route::post('/items', [ItemController::class, 'store']);
Route::put('/items/{item}', [ItemController::class, 'update']);
Route::delete('/items/{item}', [ItemController::class, 'destroy']);

Route::get('/units', [UnitController::class, 'index']);
Route::post('/units', [UnitController::class, 'store']);
Route::delete('/units/{unit}', [UnitController::class, 'destroy']);
