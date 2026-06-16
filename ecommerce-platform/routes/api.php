<?php

use App\Http\Controllers\Api\CartApiController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\ProductApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// =========================================================================
// PUBLIC ROUTES
// =========================================================================

Route::prefix('v1')->group(function () {
    
    // ── Products ─────────────────────────────────────────────────────────
    Route::get('products', [ProductApiController::class, 'index']);
    Route::get('products/featured', [ProductApiController::class, 'featured']);
    Route::get('products/autocomplete', [ProductApiController::class, 'autocomplete']);
    Route::get('products/{slug}', [ProductApiController::class, 'show']);
    Route::get('products/{slug}/related', [ProductApiController::class, 'related']);

    // =====================================================================
    // PUBLIC WEBHOOKS & CALLBACKS
    // =====================================================================
    
    // Iyzico 3D Secure Callback
    Route::post('payments/iyzico/callback', [\App\Http\Controllers\Api\PaymentApiController::class, 'callback3DS']);
    
    // Shipping Webhooks (Carrier Updates)
    Route::post('webhooks/shipping/{carrier}', [\App\Http\Controllers\Api\ShippingWebhookController::class, 'handle']);

    // =====================================================================
    // AUTHENTICATED ROUTES
    // =====================================================================
    Route::middleware('auth:sanctum')->group(function () {

        // ── Cart (Customer/User) ─────────────────────────────────────────
        Route::prefix('cart')->group(function () {
            Route::get('/', [CartApiController::class, 'index']);
            Route::delete('/', [CartApiController::class, 'clear']);
            
            Route::post('items', [CartApiController::class, 'addItem']);
            Route::put('items/{id}', [CartApiController::class, 'updateItem']);
            Route::delete('items/{id}', [CartApiController::class, 'removeItem']);
            Route::post('items/{id}/save', [CartApiController::class, 'toggleSaveForLater']);
        });

        // ── Payments (Iyzico 3D Secure) ──────────────────────────────────
        Route::prefix('payments/iyzico')->group(function () {
            Route::post('initialize', [\App\Http\Controllers\Api\PaymentApiController::class, 'initialize3DS']);
        });

        // ── Orders (Customer/User) ───────────────────────────────────────
        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderApiController::class, 'index']);
            Route::post('/', [OrderApiController::class, 'store']);
            Route::get('{orderNumber}', [OrderApiController::class, 'show']);
            Route::post('{orderNumber}/cancel', [OrderApiController::class, 'cancel']);
        });

        // =================================================================
        // VENDOR & ADMIN ROUTES
        // =================================================================
        Route::middleware('role:admin,vendor')->group(function () {
            
            // ── Product Management ───────────────────────────────────────
            Route::post('products', [ProductApiController::class, 'store']);
            Route::put('products/{id}', [ProductApiController::class, 'update']);
            Route::delete('products/{id}', [ProductApiController::class, 'destroy']);
            
            // ── Order Management (Admin/Vendor Specifics) ────────────────
            // For a real platform, you might split admin and vendor order endpoints further.
            Route::get('admin/orders', [OrderApiController::class, 'adminIndex']);
            Route::put('admin/orders/{id}/status', [OrderApiController::class, 'updateStatus']);
            Route::put('admin/orders/{id}/ship', [OrderApiController::class, 'markShipped']);
        });

    });
});
