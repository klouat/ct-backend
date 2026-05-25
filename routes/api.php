<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BoxController;
use App\Http\Controllers\Api\BoxLocationController;
use App\Http\Controllers\Api\CountingController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\QrController;
use App\Http\Controllers\Api\ScanController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\ShipmentLocationController;
use App\Http\Controllers\Api\VendorController;
use App\Http\Controllers\Api\VerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is running',
        'data' => null,
    ]);
});

Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/auth/refresh', [AuthController::class, 'refresh']);

Route::middleware('auth:api')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::middleware('role:ADMIN')->group(function () {
        Route::apiResource('vendors', VendorController::class);
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
    });

    Route::middleware('role:ADMIN|VENDOR')->group(function () {
        Route::apiResource('invoices', InvoiceController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::apiResource('shipments', ShipmentController::class);
        Route::apiResource('boxes', BoxController::class);
        Route::apiResource('packages', PackageController::class);
        Route::post('/packages/{package}/generate-qr', [QrController::class, 'generate']);
    });

    Route::middleware('role:ADMIN|OPERATOR')->group(function () {
        Route::post('/scan', [ScanController::class, 'scan'])->middleware('throttle:30,1');
        Route::post('/scan/invoices/{invoice}/pending', [ScanController::class, 'markPending'])->middleware('throttle:30,1');
        Route::post('/scan/invoices/{invoice}/complete', [ScanController::class, 'complete'])->middleware('throttle:30,1');
        Route::post('/packages/{package}/count', [CountingController::class, 'store']);
    });

    Route::middleware('role:ADMIN|OPERATOR|VENDOR')->group(function () {
        Route::get('/history', [HistoryController::class, 'index']);
        Route::get('/invoices/{invoice}/verification', [VerificationController::class, 'invoice']);
        Route::get('/packages/{package}/verification', [VerificationController::class, 'package']);
        Route::get('/boxes/{box}/verification', [VerificationController::class, 'box']);
        Route::get('/shipments/{shipment}/verification', [VerificationController::class, 'shipment']);
    });

    Route::middleware('role:ADMIN|DRIVER|VENDOR')->group(function () {
        Route::post('/boxes/{box}/locations', [BoxLocationController::class, 'store'])->middleware('throttle:60,1');
        Route::get('/boxes/{box}/locations', [BoxLocationController::class, 'index']);
        Route::get('/boxes/{box}/latest-location', [BoxLocationController::class, 'latest']);
        Route::post('/shipments/{shipment}/locations', [ShipmentLocationController::class, 'store'])->middleware('throttle:60,1');
        Route::get('/shipments/{shipment}/locations', [ShipmentLocationController::class, 'index']);
        Route::get('/shipments/{shipment}/latest-location', [ShipmentLocationController::class, 'latest']);
    });
});
