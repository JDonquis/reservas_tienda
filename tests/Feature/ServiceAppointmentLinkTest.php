<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\Store;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Google\Service\Calendar\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceAppointmentLinkTest extends TestCase
{
    use RefreshDatabase;

    private function store(): Store
    {
        return Store::create(['name' => 'Tienda', 'api_key' => 'test-key-123']);
    }

    public function test_store_services_endpoint_returns_only_active_services(): void
    {
        $store = $this->store();

        Service::create([
            'store_id' => $store->id,
            'name' => 'Masaje activo',
            'price' => 40,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        Service::create([
            'store_id' => $store->id,
            'name' => 'Servicio inactivo',
            'price' => 20,
            'duration_minutes' => 30,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/store/services', [
            'X-Store-Api-Key' => 'test-key-123',
        ]);

        $response->assertSuccessful();
        $this->assertCount(1, $response->json('services'));
        $this->assertSame('Masaje activo', $response->json('services.0.name'));
    }

    public function test_available_slots_use_service_duration(): void
    {
        $store = $this->store();

        $date = '2026-08-17';
        $day = Carbon::parse($date)->dayOfWeek;

        Schedule::create([
            'store_id' => $store->id,
            'day_of_week' => $day,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'slot_duration_minutes' => 60,
        ]);

        $service = Service::create([
            'store_id' => $store->id,
            'name' => 'Masaje 30min',
            'price' => 30,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $withoutService = $this->getJson("/api/v1/store/available-slots?date={$date}", [
            'X-Store-Api-Key' => 'test-key-123',
        ]);
        $this->assertCount(2, $withoutService->json('slots'));

        $withService = $this->getJson("/api/v1/store/available-slots?date={$date}&service_id={$service->id}", [
            'X-Store-Api-Key' => 'test-key-123',
        ]);
        $this->assertCount(4, $withService->json('slots'));
    }

    public function test_create_appointment_stores_service_id(): void
    {
        $store = $this->store();

        $service = Service::create([
            'store_id' => $store->id,
            'name' => 'Masaje relajante',
            'price' => 40,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        $this->mock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('createAppointmentEvent')
                ->once()
                ->andReturn(new Event(['id' => 'evt_1']));
        });

        $response = $this->postJson('/api/v1/store/appointments', [
            'customer_name' => 'Maria Gomez',
            'customer_email' => 'maria@example.com',
            'customer_phone' => '555-1234',
            'service_id' => $service->id,
            'start_time' => '2026-08-17 09:00:00',
            'end_time' => '2026-08-17 10:00:00',
        ], [
            'X-Store-Api-Key' => 'test-key-123',
        ]);

        $response->assertStatus(201);

        $appointment = Appointment::first();
        $this->assertSame($service->id, $appointment->service_id);
        $this->assertSame('evt_1', $appointment->google_event_id);
    }
}
