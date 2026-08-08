<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Store;
use Google\Client;
use Google\Service\Calendar;
use Illuminate\Http\Request;
use Carbon\Carbon;

class GoogleWebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        // Google envía encabezados especiales para validar y describir la notificación
        $resourceId = $request->header('X-Goog-Resource-ID');
        $channelId  = $request->header('X-Goog-Channel-ID');
        $state      = $request->header('X-Goog-Resource-State');

        // Si es una simple prueba de sincronización inicial
        if ($state === 'sync') {
            return response()->json(['message' => 'Sync ok'], 200);
        }

        // Buscar la tienda vinculada a este canal de notificación
        $store = Store::where('google_channel_id', $channelId)->first();

        if (!$store || !$store->google_refresh_token) {
            return response()->json(['error' => 'Store or token not found'], 404);
        }

        // 1. Instanciar cliente de Google con los tokens del cliente
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->refreshToken($store->google_refresh_token);

        $service = new Calendar($client);

        // 2. Obtener eventos recientes para detectar cambios/cancelaciones
        $optParams = [
            'timeMin' => Carbon::now()->subDays(1)->toRfc3339String(),
            'showDeleted' => true, // Importante para detectar borrados
        ];

        $events = $service->events->listEvents('primary', $optParams);

        foreach ($events->getItems() as $event) {
            $appointment = Appointment::where('google_event_id', $event->getId())->first();

            if (!$appointment) {
                continue;
            }

            // Si el evento fue eliminado en Google Calendar
            if ($event->getStatus() === 'cancelled') {
                if ($appointment && $appointment->status !== 'cancelled') {
                    $appointment->update([
                        'status' => 'cancelled'
                    ]);
                continue;
            }

            // Si el evento fue movido de fecha/hora en Google Calendar
            if ($event->getStart() && $event->getStart()->getDateTime()) {
                $newStart = Carbon::parse($event->getStart()->getDateTime())->format('Y-m-d H:i:s');
                $newEnd   = Carbon::parse($event->getEnd()->getDateTime())->format('Y-m-d H:i:s');

                $appointment->update([
                    'start_time' => $newStart,
                    'end_time'   => $newEnd,
                ]);
            }
        }

        return response()->json(['status' => 'success'], 200);
    }
}
}
