<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function store(): Store
    {
        return Store::create(['name' => 'Tienda', 'api_key' => 'test-key-123']);
    }

    public function test_admin_can_get_and_update_payment_settings(): void
    {
        $this->superadmin();
        $store = $this->store();

        $response = $this->getJson("/api/v1/admin/stores/{$store->id}/payment-settings");
        $response->assertSuccessful();
        $this->assertSame('USD', $response->json('currency'));
        $this->assertFalse($response->json('providers.mercadopago.enabled'));

        $this->putJson("/api/v1/admin/stores/{$store->id}/payment-settings", [
            'currency' => 'MXN',
            'providers' => [
                'mercadopago' => [
                    'enabled' => true,
                    'mode' => 'sandbox',
                    'public_key' => 'TEST-public-key',
                    'secret_key' => 'TEST-secret-key',
                ],
                'paypal' => ['enabled' => false, 'mode' => 'sandbox', 'public_key' => null, 'secret_key' => null],
                'stripe' => ['enabled' => false, 'mode' => 'sandbox', 'public_key' => null, 'secret_key' => null],
            ],
        ])->assertSuccessful();

        $response = $this->getJson("/api/v1/admin/stores/{$store->id}/payment-settings");
        $this->assertSame('MXN', $response->json('currency'));
        $this->assertTrue($response->json('providers.mercadopago.enabled'));
        $this->assertTrue($response->json('providers.mercadopago.configured'));
        $this->assertStringNotContainsString('TEST-secret-key', $response->json('providers.mercadopago.secret_key'));
    }

    public function test_create_appointment_returns_checkout_url_when_mercadopago_enabled(): void
    {
        $store = $this->store();

        $store->paymentSettings()->create([
            'provider' => 'mercadopago',
            'enabled' => true,
            'mode' => 'sandbox',
            'public_key' => 'TEST-pub',
            'secret_key' => 'TEST-secret',
        ]);

        $service = Service::create([
            'store_id' => $store->id,
            'name' => 'Masaje',
            'price' => 40,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response([
                'id' => 'pref_123',
                'init_point' => 'https://checkout.mercadopago.com/pref_123',
            ], 201),
        ]);

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
        $this->assertSame('https://checkout.mercadopago.com/pref_123', $response->json('checkout_url'));

        $appointment = Appointment::first();
        $this->assertSame('pending', $appointment->status);
        $this->assertSame('pending', $appointment->payment_status);
    }

    public function test_mercadopago_webhook_marks_appointment_paid(): void
    {
        $store = $this->store();

        $store->paymentSettings()->create([
            'provider' => 'mercadopago',
            'enabled' => true,
            'mode' => 'sandbox',
            'secret_key' => 'TEST-secret',
        ]);

        $service = Service::create([
            'store_id' => $store->id,
            'name' => 'Masaje',
            'price' => 40,
            'duration_minutes' => 60,
        ]);

        $appointment = Appointment::create([
            'store_id' => $store->id,
            'service_id' => $service->id,
            'customer_name' => 'Maria Gomez',
            'customer_email' => 'maria@example.com',
            'customer_phone' => '555-1234',
            'start_time' => '2026-08-17 09:00:00',
            'end_time' => '2026-08-17 10:00:00',
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $payment = Payment::create([
            'store_id' => $store->id,
            'provider' => 'mercadopago',
            'provider_payment_id' => 'payment_123',
            'status' => 'pending',
            'amount' => 40,
            'currency' => 'USD',
            'payable_type' => $appointment->getMorphClass(),
            'payable_id' => $appointment->id,
        ]);

        Http::fake([
            'api.mercadopago.com/v1/payments/payment_123' => Http::response([
                'id' => 'payment_123',
                'status' => 'approved',
                'external_reference' => (string) $payment->id,
            ], 200),
        ]);

        $this->postJson("/api/v1/payments/mercadopago/webhook/{$store->id}", [
            'type' => 'payment',
            'data' => ['id' => 'payment_123'],
        ])->assertSuccessful();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);
    }
}
