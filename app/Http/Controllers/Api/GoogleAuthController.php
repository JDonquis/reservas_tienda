<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Google\Client;
use Google\Service\Calendar;
use Illuminate\Http\Request;

class GoogleAuthController extends Controller
{
    protected function getGoogleClient(): Client
    {
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));
        $client->addScope([
            Calendar::CALENDAR,
            Calendar::CALENDAR_EVENTS,
        ]);
        // Parámetros obligatorios para obtener el refresh_token
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        return $client;
    }

    // 1. Redirige al cliente a la pantalla de login de Google
    public function redirect(Request $request)
    {
        $apiKey = $request->query('api_key');

        if (!$apiKey || !Store::where('api_key', $apiKey)->exists()) {
            return response()->json(['error' => 'API Key no válida'], 400);
        }

        $client = $this->getGoogleClient();
        // Guardamos la api_key en el parámetro state para saber qué tienda está vinculando
        $client->setState($apiKey);

        return redirect()->away($client->createAuthUrl());
    }

    // 2. Google devuelve al usuario a esta ruta con un código de autorización
    public function callback(Request $request)
    {
        $code = $request->query('code');
        $apiKey = $request->query('state');

        if (!$code || !$apiKey) {
            return response()->json(['error' => 'Autorización denegada o incompleta'], 400);
        }

        $store = Store::where('api_key', $apiKey)->first();

        if (!$store) {
            return response()->json(['error' => 'Tienda no encontrada'], 404);
        }

        $client = $this->getGoogleClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            return response()->json(['error' => 'Error al obtener el token de Google'], 500);
        }

        // Guardamos los tokens en la base de datos de esa tienda específica
        $store->update([
            'google_access_token' => json_encode($token),
            'google_refresh_token' => $token['refresh_token'] ?? $store->google_refresh_token,
        ]);


        $client = $this->getGoogleClient();
        $client->refreshToken($store->google_refresh_token);
        $service = new Calendar($client);

        $channel = new Calendar\Channel();
        $channel->setId('store_channel_' . $store->id . '_' . time());
        $channel->setType('web_hook');
        // La URL pública donde Google notificará los cambios
        $channel->setAddress(config('app.url') . '/api/v1/google/webhook');

        $watchResponse = $service->events->watch('primary', $channel);

        // Guardar el id del canal en la tienda para asociarlo al recibir la notificación
        $store->update([
            'google_channel_id' => $channel->getId(),
        ]);


        return response()->json([
            'message' => '¡Google Calendar vinculado con éxito para la tienda ' . $store->name . '!',
        ]);
    }
}
