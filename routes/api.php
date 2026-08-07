<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StoreApiController;
use App\Http\Controllers\Api\AppointmentApiController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\GoogleWebhookController;

Route::prefix('v1')->group(function () {
    Route::get('/store/catalog', [StoreApiController::class, 'getCatalog']);
    Route::post('/store/appointments', [AppointmentApiController::class, 'createAppointment']);
    Route::get('/store/available-slots', [AppointmentApiController::class, 'getAvailableSlots']);

    // Rutas de Vinculación de Google Calendar
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);
    Route::post('/v1/google/webhook', [GoogleWebhookController::class, 'handleWebhook']);
});
