<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Schedule;
use Carbon\Carbon;

class AppointmentApiController extends Controller
{
    public function createAppointment(Request $request, GoogleCalendarService $calendarService)
    {
        $apiKey = $request->header('X-Store-Api-Key');

        if (!$apiKey) {
            return response()->json(['error' => 'API Key requerida'], 400);
        }

        $store = Store::where('api_key', $apiKey)->first();

        if (!$store) {
            return response()->json(['error' => 'Tienda no válida'], 404);
        }

        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:50',
            'start_time' => 'required|date_format:Y-m-d H:i:s',
            'end_time' => 'required|date_format:Y-m-d H:i:s|after:start_time',
        ]);

        $appointment = $store->appointments()->create([
            'customer_name' => $request->customer_name,
            'customer_email' => $request->customer_email,
            'customer_phone' => $request->customer_phone,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'status' => 'confirmed',
        ]);

        // Sincronización con Google Calendar
        try {
            $googleEvent = $calendarService->createAppointmentEvent($store, [
                'customer_name' => $appointment->customer_name,
                'customer_email' => $appointment->customer_email,
                'customer_phone' => $appointment->customer_phone,
                'start_time' => $appointment->start_time,
                'end_time' => $appointment->end_time,
            ]);

            if ($googleEvent) {
                $appointment->update(['google_event_id' => $googleEvent->id]);
            }
        } catch (\Exception $e) {
            Log::error('Error registrando evento en Google Calendar: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Reserva creada con éxito',
            'appointment_id' => $appointment->id,
        ], 201);
    }

    public function getAvailableSlots(Request $request)
    {
        $apiKey = $request->header('X-Store-Api-Key');
        $store = Store::where('api_key', $apiKey)->firstOrFail();

        $date = $request->query('date'); // Formato 'YYYY-MM-DD'
        if (!$date) {
            return response()->json(['error' => 'La fecha es obligatoria'], 400);
        }

        $carbonDate = Carbon::parse($date);
        $dayOfWeek = $carbonDate->dayOfWeek; // 0 (Dom) - 6 (Sáb)

        // 1. Obtener horario asignado para ese día de la semana
        $schedules = $store->schedules()->where('day_of_week', $dayOfWeek)->get();

        if ($schedules->isEmpty()) {
            return response()->json(['slots' => []]); // Cerrado este día
        }

        // 2. Generar todos los bloques (slots) posibles dentro del horario
        $possibleSlots = [];
        foreach ($schedules as $schedule) {
            $start = Carbon::parse($date . ' ' . $schedule->start_time);
            $end = Carbon::parse($date . ' ' . $schedule->end_time);
            $duration = $schedule->slot_duration_minutes;

            while ($start->copy()->addMinutes($duration)->lte($end)) {
                $slotStart = $start->format('Y-m-d H:i:s');
                $slotEnd = $start->copy()->addMinutes($duration)->format('Y-m-d H:i:s');

                $possibleSlots[] = [
                    'start' => $slotStart,
                    'end' => $slotEnd,
                    'time_label' => $start->format('H:i'),
                ];

                $start->addMinutes($duration);
            }
        }

        // 3. Consultar las citas existentes en la DB o Google Calendar para descartar horas ocupadas
        $existingAppointments = $store->appointments()
            ->whereDate('start_time', $date)
            ->where('status', '!=', 'cancelled')
            ->get();

        $availableSlots = array_filter($possibleSlots, function ($slot) use ($existingAppointments) {
            foreach ($existingAppointments as $app) {
                // Verificar si el slot se solapa con una cita existente
                if ($slot['start'] < $app->end_time && $slot['end'] > $app->start_time) {
                    return false; // Hora ocupada
                }
            }
            return true; // Hora libre
        });

        return response()->json([
            'date' => $date,
            'slots' => array_values($availableSlots)
        ]);
    }

    public function cancelAppointment(Request $request, $id, GoogleCalendarService $calendarService)
    {
        $apiKey = $request->header('X-Store-Api-Key');

        if (!$apiKey) {
            return response()->json(['error' => 'API Key requerida'], 400);
        }

        $store = Store::where('api_key', $apiKey)->first();

        if (!$store) {
            return response()->json(['error' => 'Tienda no válida'], 404);
        }

        // 1. Buscar la cita de esta tienda específica
        $appointment = $store->appointments()->where('id', $id)->first();

        if (!$appointment) {
            return response()->json(['error' => 'Cita no encontrada'], 404);
        }

        if ($appointment->status === 'cancelled') {
            return response()->json(['message' => 'La cita ya se encuentra cancelada'], 200);
        }

        // 2. Eliminar el evento en Google Calendar si existe un ID de evento vinculado
        if ($appointment->google_event_id) {
            try {
                $calendarService->deleteAppointmentEvent($store, $appointment->google_event_id);
            } catch (\Exception $e) {
                Log::error('Error al eliminar evento en Google Calendar: ' . $e->getMessage());
            }
        }

        // 3. Actualizar estado en MySQL
        $appointment->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'message' => 'Cita cancelada exitosamente',
            'appointment_id' => $appointment->id,
        ], 200);
    }


}
