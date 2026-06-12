<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BoxController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ScanController;
use App\Http\Controllers\Api\VendorController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/public/vendors', [VendorController::class, 'publicList']);

Route::middleware('auth:api')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::middleware('role:ADMIN')->group(function () {
        Route::get('/vendors', [VendorController::class, 'index']);
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
