<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuthRefreshController;
use App\Http\Controllers\Api\BoxController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ScanController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VendorController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/auth/refresh', AuthRefreshController::class)->middleware('throttle:20,1');
Route::get('/public/vendors', [VendorController::class, 'publicList']);

Route::middleware('auth:api')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::middleware('role:ADMIN')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::get('/vendors', [VendorController::class, 'index']);
        Route::post('/vendors', [VendorController::class, 'store']);
        Route::put('/vendors/{vendor}', [VendorController::class, 'update']);
        Route::delete('/vendors/{vendor}', [VendorController::class, 'destroy']);
        Route::post('/invoices', [InvoiceController::class, 'store']);
        Route::get('/boxes', [BoxController::class, 'index']);
    });

    Route::post('/audit-logs/activity', [AuditLogController::class, 'storeActivity']);

    Route::middleware('role:ADMIN|SUPERVISOR')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/audit-logs/users', [AuditLogController::class, 'searchUsers']);
        Route::get('/history', [HistoryController::class, 'index']);
    });

    Route::middleware('role:ADMIN|PETUGAS_GUDANG')->group(function () {
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::post('/scan', [ScanController::class, 'scan'])->middleware('throttle:30,1');
        Route::post('/scan/invoices/{invoice}/confirm', [ScanController::class, 'confirm'])->middleware('throttle:30,1');
        Route::post('/scan/invoices/{invoice}/pending', [ScanController::class, 'markPending'])->middleware('throttle:30,1');
        Route::post('/scan/invoices/{invoice}/complete', [ScanController::class, 'complete'])->middleware('throttle:30,1');
    });

    Route::middleware('role:VENDOR')->group(function () {
        Route::get('/qr-preview', [InvoiceController::class, 'qrPreview']);
        Route::post('/invoices/{invoice}/accept', [InvoiceController::class, 'accept']);
        Route::post('/invoices/{invoice}/reject', [InvoiceController::class, 'reject']);
    });
});
