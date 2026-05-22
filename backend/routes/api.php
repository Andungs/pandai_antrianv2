<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DisplayController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\Admin\AdminCounterController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminServiceController;
use App\Http\Controllers\Admin\AdminSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Pandai Antrian
|--------------------------------------------------------------------------
| Struktur:
|   /api/auth/*          → Public auth endpoints
|   /api/admin/*         → Superadmin only (auth + role:superadmin)
|   /api/loket/*         → Petugas loket (auth + role:loket)
|   /api/guest/*         → Public (KIOSK, Display TV, Tracking)
|   /api/me              → Current user data (auth)
*/

// === AUTH (Public) ===
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// === GUEST / PUBLIC (KIOSK, Display, Tracking) ===
Route::prefix('guest')->group(function () {
    // KIOSK
    Route::get('/services', [DisplayController::class, 'services']);
    Route::post('/queues', [QueueController::class, 'store']);

    // Display TV
    Route::get('/display', [DisplayController::class, 'index']);

    // Tracking via QR
    Route::get('/queues/{queueNumber}/track', [QueueController::class, 'track']);

    // QZTray Certificate & Signing
    Route::get('/qz-certs', [\App\Http\Controllers\QzTrayController::class, 'certificate']);
    Route::get('/qz-sign', [\App\Http\Controllers\QzTrayController::class, 'sign']);

    // Public settings (QZTray certificate)
    Route::get('/settings/qztray-certificate', [AdminSettingController::class, 'downloadQzTrayCertificate']);
});

// === PROTECTED ROUTES ===
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // === ADMIN (Superadmin only) ===
    Route::prefix('admin')->middleware('role:superadmin')->group(function () {
        // Dashboard Analytics
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        Route::get('/dashboard/history', [AdminDashboardController::class, 'history']);

        // CRUD Users
        Route::apiResource('users', \App\Http\Controllers\Admin\AdminUserController::class);

        // CRUD Layanan
        Route::apiResource('services', AdminServiceController::class);

        // CRUD Loket
        Route::apiResource('counters', AdminCounterController::class);
        Route::get('/counters-form-data', [AdminCounterController::class, 'formData']);

        // Settings
        Route::get('/settings', [AdminSettingController::class, 'index']);
        Route::put('/settings', [AdminSettingController::class, 'update']);
        Route::post('/settings/upload-logo', [AdminSettingController::class, 'uploadLogo']);
        Route::post('/settings/qztray-certificate', [AdminSettingController::class, 'generateQzTrayCertificate']);
    });

    // === LOKET (Petugas + Superadmin) ===
    Route::prefix('loket')->middleware('role:loket,superadmin')->group(function () {
        Route::get('/queues/current', [QueueController::class, 'current']);
        Route::post('/queues/next', [QueueController::class, 'next']);
        Route::post('/queues/{id}/recall', [QueueController::class, 'recall']);
        Route::post('/queues/{id}/serve', [QueueController::class, 'serve']);
        Route::post('/queues/{id}/skip', [QueueController::class, 'skip']);
        Route::get('/queues/history', [QueueController::class, 'history']);
        // Superadmin: pilih counter mana yang mau dioperasikan
        Route::post('/select-counter/{counterId}', [QueueController::class, 'selectCounter']);
        Route::get('/available-counters', [QueueController::class, 'availableCounters']);
    });
});
