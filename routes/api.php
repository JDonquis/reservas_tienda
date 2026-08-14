<?php

use App\Http\Controllers\Api\Admin\StoreController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AppointmentApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\GoogleWebhookController;
use App\Http\Controllers\Api\StoreApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::get('/', function () {
        return response()->json([
            'message' => 'API de Reservas de Tienda - Versión 1',
            'status' => 'success',
        ]);
    });

    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);

        // Login / Login Social con Google para acceder al sistema
        Route::get('/google', [AuthController::class, 'redirectToGoogle']);
        Route::get('/google/callback', [AuthController::class, 'handleGoogleCallback']);
    });

    // =========================================================================
    // 2. RUTAS PROTEGIDAS DEL PANEL DE ADMINISTRACIÓN (Sanctum)
    // =========================================================================
    Route::middleware('auth:sanctum')->group(function () {

        // Datos del Usuario Autenticado
        Route::get('/user/store', [AuthController::class, 'userStore']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // VINCULACIÓN OAUTH DE GOOGLE CALENDAR (Para sincronizar la tienda)
        // Se cambió a /google-calendar para evitar choque con el Login de Google
        Route::prefix('google-calendar')->group(function () {
            Route::get('/redirect', [GoogleAuthController::class, 'redirect']);
            Route::get('/callback', [GoogleAuthController::class, 'callback']);
        });

        // =====================================================================
        // ADMINISTRACIÓN DEL PANEL: USUARIOS (solo superadmin)
        // =====================================================================
        Route::middleware('role:superadmin')->group(function () {
            Route::apiResource('admin/users', UserController::class);
        });

        // =====================================================================
        // ADMINISTRACIÓN DEL PANEL: TIENDAS (superadmin total; store_owner su tienda)
        // =====================================================================
        Route::get('admin/stores', [StoreController::class, 'index']);
        Route::post('admin/stores', [StoreController::class, 'store'])->middleware('role:superadmin');
        Route::get('admin/stores/{store}', [StoreController::class, 'show']);
        Route::put('admin/stores/{store}', [StoreController::class, 'update']);
        Route::delete('admin/stores/{store}', [StoreController::class, 'destroy'])->middleware('role:superadmin');
        Route::post('admin/stores/{store}/regenerate-key', [StoreController::class, 'regenerateKey']);
    });

    // =========================================================================
    // 3. RUTAS PÚBLICAS PARA EL WIDGET / PLUGIN (API Key + Dominio Autorizado)
    // =========================================================================
    Route::middleware(['validate.store.domain'])->prefix('store')->group(function () {
        Route::get('/catalog', [StoreApiController::class, 'getCatalog']);
        Route::get('/available-slots', [AppointmentApiController::class, 'getAvailableSlots']);
        Route::post('/appointments', [AppointmentApiController::class, 'createAppointment']);
        Route::delete('/appointments/{id}', [AppointmentApiController::class, 'cancelAppointment']);
    });

    // =========================================================================
    // 4. WEBHOOKS Y CALLBACKS EXTERNOS (Sin Sanctum, llamados por servidores)
    // =========================================================================
    Route::post('/google/webhook', [GoogleWebhookController::class, 'handleWebhook']);
});
