<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesStoreAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Store;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AppointmentController extends Controller
{
    use AuthorizesStoreAccess;

    public function index(Request $request, Store $store)
    {
        $this->authorizeStore($request->user(), $store);

        $appointments = $store->appointments()
            ->when($request->query('date'), fn ($q, $date) => $q->whereDate('start_time', $date))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('q'), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('start_time')
            ->paginate($request->integer('per_page', 20));

        return AppointmentResource::collection($appointments);
    }

    public function show(Request $request, Store $store, Appointment $appointment)
    {
        $this->authorizeStore($request->user(), $store);
        $this->ensureBelongsToStore($store, $appointment);

        return AppointmentResource::make($appointment);
    }

    public function cancel(Request $request, Store $store, Appointment $appointment, GoogleCalendarService $calendarService)
    {
        $this->authorizeStore($request->user(), $store);
        $this->ensureBelongsToStore($store, $appointment);

        if ($appointment->status === 'cancelled') {
            return response()->json(['message' => 'La cita ya se encuentra cancelada.'], 200);
        }

        if ($appointment->google_event_id) {
            try {
                $calendarService->deleteAppointmentEvent($store, $appointment->google_event_id);
            } catch (\Exception $e) {
                Log::error('Error al eliminar evento en Google Calendar: '.$e->getMessage());
            }
        }

        $appointment->update(['status' => 'cancelled']);

        return AppointmentResource::make($appointment);
    }
}
