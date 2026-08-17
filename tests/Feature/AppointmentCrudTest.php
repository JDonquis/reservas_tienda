<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentCrudTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_superadmin_can_list_appointments(): void
    {
        $this->superadmin();
        $store = Store::create(['name' => 'Tienda']);

        Appointment::create([
            'store_id' => $store->id,
            'customer_name' => 'Maria Gomez',
            'customer_email' => 'maria@example.com',
            'customer_phone' => '555-1234',
            'start_time' => '2026-08-20 09:00:00',
            'end_time' => '2026-08-20 10:00:00',
            'status' => 'confirmed',
        ]);

        $response = $this->getJson("/api/v1/admin/stores/{$store->id}/appointments?date=2026-08-20");

        $response->assertSuccessful();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_superadmin_can_cancel_appointment(): void
    {
        $this->superadmin();
        $store = Store::create(['name' => 'Tienda']);

        $appointment = Appointment::create([
            'store_id' => $store->id,
            'customer_name' => 'Carlos Ruiz',
            'customer_email' => 'carlos@example.com',
            'customer_phone' => '555-5678',
            'start_time' => '2026-08-20 11:00:00',
            'end_time' => '2026-08-20 12:00:00',
            'status' => 'confirmed',
        ]);

        $this->postJson("/api/v1/admin/stores/{$store->id}/appointments/{$appointment->id}/cancel")
            ->assertSuccessful();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_store_owner_cannot_access_other_store_appointments(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner']);
        Sanctum::actingAs($owner);

        $foreignStore = Store::create(['name' => 'Otra tienda']);

        $this->getJson("/api/v1/admin/stores/{$foreignStore->id}/appointments")
            ->assertStatus(403);
    }
}
