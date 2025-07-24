<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\TableController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\ReceiptController;
use App\Http\Controllers\API\StatisticsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('v1')->group(function () {
    // Authentication routes
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Protected routes
    Route::middleware(['auth:sanctum', 'check.active.user'])->group(function () {
        // Auth routes
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/profile', [AuthController::class, 'profile']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);

        // Categories (all authenticated users can view)
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/categories/{id}', [CategoryController::class, 'show']);

        // Products (all authenticated users can view)
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{id}', [ProductController::class, 'show']);

        // Tables (all authenticated users can view available tables)
        Route::get('/tables', [TableController::class, 'index']);
        Route::get('/tables/available', [TableController::class, 'getAvailableTables']);
        Route::get('/tables/{id}', [TableController::class, 'show']);

        // Orders
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);

        // Receipts (users can view their own receipts)
        Route::get('/receipts/{id}', [ReceiptController::class, 'show']);
        Route::get('/receipts/{id}/download', [ReceiptController::class, 'download']);

        // Admin only routes
        Route::middleware(['role:admin'])->group(function () {
            // User management
            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::get('/users/{id}', [UserController::class, 'show']);
            Route::put('/users/{id}', [UserController::class, 'update']);
            Route::delete('/users/{id}', [UserController::class, 'destroy']);
            Route::patch('/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
        });

        // Cashier and Admin routes
        Route::middleware(['role:cashier,admin'])->group(function () {
            // Category management
            Route::post('/categories', [CategoryController::class, 'store']);
            Route::put('/categories/{id}', [CategoryController::class, 'update']);
            Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

            // Product management
            Route::post('/products', [ProductController::class, 'store']);
            Route::put('/products/{id}', [ProductController::class, 'update']);
            Route::delete('/products/{id}', [ProductController::class, 'destroy']);
            Route::patch('/products/{id}/toggle-availability', [ProductController::class, 'toggleAvailability']);
            Route::patch('/products/{id}/update-stock', [ProductController::class, 'updateStock']);

            // Table management
            Route::post('/tables', [TableController::class, 'store']);
            Route::put('/tables/{id}', [TableController::class, 'update']);
            Route::delete('/tables/{id}', [TableController::class, 'destroy']);
            Route::patch('/tables/{id}/status', [TableController::class, 'updateStatus']);

            // Order management
            Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
            Route::patch('/orders/{id}/payment-status', [OrderController::class, 'updatePaymentStatus']);
            Route::delete('/orders/{id}', [OrderController::class, 'destroy']);

            // Receipt management
            Route::get('/receipts', [ReceiptController::class, 'index']);
            Route::post('/receipts', [ReceiptController::class, 'store']);
            Route::delete('/receipts/{id}', [ReceiptController::class, 'destroy']);

            // Statistics and reports
            Route::get('/statistics/dashboard', [StatisticsController::class, 'dashboard']);
            Route::get('/statistics/sales-report', [StatisticsController::class, 'salesReport']);
            Route::get('/statistics/export-sales-report', [StatisticsController::class, 'exportSalesReport']);
        });
    });
});

// Fallback route for API
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found'
    ], 404);
});
