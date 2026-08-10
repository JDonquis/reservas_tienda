<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Store;
use Google\Client;
use Google\Service\Calendar;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GoogleWebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $resourceId = $request->header('X-Goog-Resource-ID');
        $channelId  = $request->header('X-Goog-Channel-ID');
        $state      = $request->header('X-Goog-Resource-State');

        // Handshake de confirmación inicial de Google
        if ($state === 'sync') {
            return response()->json(['message' => 'Sync ok'], 200);
        }

        // Buscar la tienda por canal
        $store = Store::where('google_channel_id', $channelId)->first();

        if (!$store || !$store->google_refresh_token) {
            return response()->json(['error' => 'Store or token not found'], 404);
        }

        try {
            // 1. Autenticar cliente de Google
            $client = new Client();
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));
            $client->refreshToken($store->google_refresh_token);

            $service = new Calendar($client);

            // 2. Usar updatedMin para traer todo lo modificado en los últimos 5 minutos
            // Esto atrapa cambios de fecha a cualquier mes o año futuro.
            $optParams = [
                'updatedMin'  => Carbon::now()->subMinutes(5)->toRfc3339String(),
                'showDeleted' => true,
            ];

            $calendarId = $store->google_calendar_id ?? 'primary';
            $events = $service->events->listEvents($calendarId, $optParams);

            foreach ($events->getItems() as $event) {
                $appointment = Appointment::where('google_event_id', $event->getId())->first();

                if (!$appointment) {
                    continue;
                }

                // CASO A: Evento Eliminado/Cancelado en Google Calendar
                if ($event->getStatus() === 'cancelled') {
                    if ($appointment->status !== 'cancelled') {
                        $appointment->update(['status' => 'cancelled']);
                    }
                    continue; // Pasa a la siguiente cita
                }

                // CASO B: Evento Movido / Reprogramado
                $startObj = $event->getStart();
                $endObj   = $event->getEnd();

                if ($startObj && ($startObj->getDateTime() || $startObj->getDate())) {
                    // Soporte para citas con hora específica o de todo el día
                    $rawStart = $startObj->getDateTime() ?? $startObj->getDate();
                    $rawEnd   = $endObj->getDateTime() ?? $endObj->getDate();

                    $newStart = Carbon::parse($rawStart)->format('Y-m-d H:i:s');
                    $newEnd   = Carbon::parse($rawEnd)->format('Y-m-d H:i:s');

                    $appointment->update([
                        'start_time' => $newStart,
                        'end_time'   => $newEnd,
                    ]);
                }
            }

            return response()->json(['status' => 'success'], 200);
        } catch (\Exception $e) {
            Log::error("Error procesando Webhook Google Calendar: " . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}

