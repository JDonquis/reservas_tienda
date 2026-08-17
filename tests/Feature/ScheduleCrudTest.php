<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScheduleCrudTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_superadmin_can_create_schedule(): void
    {
        $this->superadmin();
        $store = Store::create(['name' => 'Tienda']);

        $response = $this->postJson("/api/v1/admin/stores/{$store->id}/schedules", [
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'slot_duration_minutes' => 60,
        ]);

        $response->assertSuccessful();

        $this->assertDatabaseHas('schedules', [
            'store_id' => $store->id,
            'day_of_week' => 1,
            'slot_duration_minutes' => 60,
        ]);
    }

    public function test_superadmin_can_update_schedule(): void
    {
        $this->superadmin();
        $store = Store::create(['name' => 'Tienda']);
        $schedule = Schedule::create([
            'store_id' => $store->id,
            'day_of_week' => 2,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'slot_duration_minutes' => 60,
        ]);

        $response = $this->putJson("/api/v1/admin/stores/{$store->id}/schedules/{$schedule->id}", [
            'start_time' => '10:00',
            'end_time' => '18:00',
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('data.start_time', '10:00');
        $response->assertJsonPath('data.end_time', '18:00');
    }

    public function test_store_owner_cannot_access_other_store_schedules(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner']);
        Sanctum::actingAs($owner);

        $foreignStore = Store::create(['name' => 'Otra tienda']);

        $this->getJson("/api/v1/admin/stores/{$foreignStore->id}/schedules")
            ->assertStatus(403);
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $this->superadmin();
        $store = Store::create(['name' => 'Tienda']);

        $this->postJson("/api/v1/admin/stores/{$store->id}/schedules", [
            'day_of_week' => 1,
            'start_time' => '17:00',
            'end_time' => '09:00',
            'slot_duration_minutes' => 60,
        ])->assertStatus(422);
    }
}
