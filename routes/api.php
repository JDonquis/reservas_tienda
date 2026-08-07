<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StoreApiController;
use App\Http\Controllers\Api\AppointmentApiController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\GoogleWebhookController;

Route::prefix('v1')->group(function () {

    // 1. RUTAS PÚBLICAS PARA EL WIDGET/PLUGIN (Requieren API Key + Dominio Autorizado)
    Route::middleware(['validate.store.domain'])->group(function () {
        Route::get('/store/catalog', [StoreApiController::class, 'getCatalog']);
        Route::get('/store/available-slots', [AppointmentApiController::class, 'getAvailableSlots']);
        Route::post('/store/appointments', [AppointmentApiController::class, 'createAppointment']);
        Route::delete('/store/appointments/{id}', [AppointmentApiController::class, 'cancelAppointment']);
    });

    // 2. RUTAS DE VINCULACIÓN OAUTH CON GOOGLE (Autenticación del Dueño de la Tienda)
    Route::prefix('auth/google')->group(function () {
        Route::get('/redirect', [GoogleAuthController::class, 'redirect']);
        Route::get('/callback', [GoogleAuthController::class, 'callback']);
    });

    // 3. RUTAS DE SISTEMA Y WEBHOOKS (Llamadas por servidores externos como Google)
    Route::post('/google/webhook', [GoogleWebhookController::class, 'handleWebhook']);

});
