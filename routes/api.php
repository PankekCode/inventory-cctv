<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ImportController;
use Illuminate\Support\Facades\Route;


Route::prefix('auth')->group(function () {

    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function () {

    Route::apiResource('categories', CategoryController::class);

    Route::apiResource('suppliers', SupplierController::class);

    Route::apiResource('items', ItemController::class);

    Route::post('stock-in', [StockController::class, 'stockIn']
    );

    Route::post('stock-out',[StockController::class, 'stockOut']
    );

    Route::apiResource('stock-movements',StockMovementController::class)->only([
        'index',
        'show'
    ]);

    Route::get('dashboard',[DashboardController::class, 'index']
    );

});

Route::post(
    '/import/supplier',
    [ImportController::class,'supplier']
);

Route::post(
    '/import/item',
    [ImportController::class,'item']
);

Route::post(
    '/import/price',
    [ImportController::class,'itemPrice']
);

Route::post(
    '/import/serial-number',
    [ImportController::class,'serialNumber']
);


