<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\Storefront\CartController;
use App\Http\Controllers\Api\Storefront\CatalogController;
use App\Http\Controllers\Api\Storefront\CheckoutController;
use App\Http\Controllers\Api\Storefront\CustomerAuthController;
use App\Http\Controllers\Api\Storefront\OrderController;
use App\Http\Controllers\Api\Storefront\OtpController;
use App\Http\Controllers\Api\Storefront\PageController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\ProductImageController;
use Illuminate\Support\Facades\Route;


// Internal Inventory Management API (Admin)

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

    Route::post('stock-in', [StockController::class, 'stockIn']);
    Route::post('stock-out', [StockController::class, 'stockOut']);
    Route::apiResource('stock-movements', StockMovementController::class)->only(['index', 'show']);
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::post('/products/{product}/images', [ProductImageController::class, 'store']);
});

// Import Excel
Route::post('/import/supplier', [ImportController::class, 'supplier']);
Route::post('/import/item', [ImportController::class, 'item']);
Route::post('/import/price', [ImportController::class, 'itemPrice']);
// Route::post('/import/serial-number', [ImportController::class, 'serialNumber']);


// E-Commerce Storefront API (Hablun CCTV Customer App)

Route::prefix('storefront')->group(function () {
    // Public Catalog & Content
    Route::get('/home', [CatalogController::class, 'home']);
    Route::get('/products', [CatalogController::class, 'index']);
    Route::get('/products/{slug}', [CatalogController::class, 'show']);
    Route::get('/categories', [CatalogController::class, 'categories']);
    Route::get('/brands', [CatalogController::class, 'brands']);
    Route::get('/company-profile', [PageController::class, 'companyProfile']);
    Route::get('/services', [PageController::class, 'services']);

    // WhatsApp OTP & Phone Auth
    Route::post('/otp/send', [OtpController::class, 'send']);
    Route::post('/otp/verify', [OtpController::class, 'verify']);
    Route::post('/auth/login-otp', [CustomerAuthController::class, 'loginWithOtp']);

    // Public Cart & Checkout (Guest support)
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::put('/cart/items/{id}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{id}', [CartController::class, 'removeItem']);

    Route::post('/checkout', [CheckoutController::class, 'process']);
    Route::get('/orders/track/{code}', [OrderController::class, 'track']);

    // Authenticated Customer Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [CustomerAuthController::class, 'me']);
        Route::put('/auth/profile', [CustomerAuthController::class, 'updateProfile']);
        Route::put('/auth/change-password', [CustomerAuthController::class, 'changePassword']);
        Route::post('/auth/logout', [CustomerAuthController::class, 'logout']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    });
});
