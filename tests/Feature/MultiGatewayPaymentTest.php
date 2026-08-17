<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MultiGatewayPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function store(): Store
    {
        return Store::create(['name' => 'Tienda', 'api_key' => 'test-key-123']);
    }

    private function service(Store $store, int $price = 40): Service
    {
        return Service::create([
            'store_id' => $store->id,
            'name' => 'Masaje',
            'price' => $price,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);
    }

    private function bookingPayload(Service $service): array
    {
        return [
            'customer_name' => 'Maria Gomez',
            'customer_email' => 'maria@example.com',
            'customer_phone' => '555-1234',
            'service_id' => $service->id,
            'start_time' => '2026-08-17 09:00:00',
            'end_time' => '2026-08-17 10:00:00',
        ];
    }

    public function test_create_appointment_returns_checkout_url_when_stripe_enabled(): void
    {
        $store = $this->store();
        $store->paymentSettings()->create([
            'provider' => 'stripe',
            'enabled' => true,
            'mode' => 'sandbox',
            'public_key' => 'pk_test',
            'secret_key' => 'sk_test',
            'webhook_secret' => 'whsec_test',
        ]);
        $service = $this->service($store);

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_123',
                'url' => 'https://checkout.stripe.com/cs_123',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/store/appointments', $this->bookingPayload($service), [
            'X-Store-Api-Key' => 'test-key-123',
        ]);

        $response->assertStatus(201);
        $this->assertSame('https://checkout.stripe.com/cs_123', $response->json('checkout_url'));

        $payment = Payment::first();
        $this->assertSame('stripe', $payment->provider);
        $this->assertSame('cs_123', $payment->provider_payment_id);
        $this->assertSame('pending', Appointment::first()->payment_status);
    }

    public function test_stripe_webhook_with_valid_signature_marks_appointment_paid(): void
    {
        $store = $this->store();
        $store->paymentSettings()->create([
            'provider' => 'stripe',
            'enabled' => true,
            'mode' => 'sandbox',
            'secret_key' => 'sk_test',
            'webhook_secret' => 'whsec_test',
        ]);

        $appointment = Appointment::create([
            'store_id' => $store->id,
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
            'provider' => 'stripe',
            'provider_payment_id' => 'cs_123',
            'status' => 'pending',
            'amount' => 40,
            'currency' => 'USD',
            'payable_type' => $appointment->getMorphClass(),
            'payable_id' => $appointment->id,
        ]);

        $payload = [
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_123']],
        ];
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.json_encode($payload), 'whsec_test');

        Http::fake([
            'api.stripe.com/v1/checkout/sessions/cs_123' => Http::response([
                'id' => 'cs_123',
                'payment_status' => 'paid',
                'client_reference_id' => (string) $payment->id,
            ], 200),
        ]);

        $this->postJson("/api/v1/payments/stripe/webhook/{$store->id}", $payload, [
            'Stripe-Signature' => "t={$timestamp},v1={$signature}",
        ])->assertSuccessful();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);
    }

    public function test_stripe_webhook_rejects_invalid_signature(): void
    {
        $store = $this->store();
        $store->paymentSettings()->create([
            'provider' => 'stripe',
            'enabled' => true,
            'mode' => 'sandbox',
            'secret_key' => 'sk_test',
            'webhook_secret' => 'whsec_test',
        ]);

        $payload = ['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_123']]];

        $this->postJson("/api/v1/payments/stripe/webhook/{$store->id}", $payload, [
            'Stripe-Signature' => 't=1,v1=not-a-valid-signature',
        ])->assertStatus(400);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_create_appointment_returns_checkout_url_when_paypal_enabled(): void
    {
        $store = $this->store();
        $store->paymentSettings()->create([
            'provider' => 'paypal',
            'enabled' => true,
            'mode' => 'sandbox',
            'public_key' => 'client-id',
            'secret_key' => 'client-secret',
            'webhook_secret' => 'WH-123',
        ]);
        $service = $this->service($store);

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token'], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'ORDER-123',
                'links' => [
                    ['rel' => 'self', 'href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-123'],
                    ['rel' => 'approve', 'href' => 'https://www.paypal.com/checkoutnow?token=ORDER-123'],
                ],
            ], 201),
        ]);

        $response = $this->postJson('/api/v1/store/appointments', $this->bookingPayload($service), [
            'X-Store-Api-Key' => 'test-key-123',
        ]);

        $response->assertStatus(201);
        $this->assertSame('https://www.paypal.com/checkoutnow?token=ORDER-123', $response->json('checkout_url'));

        $payment = Payment::first();
        $this->assertSame('paypal', $payment->provider);
        $this->assertSame('ORDER-123', $payment->provider_payment_id);
    }

    public function test_paypal_webhook_verifies_and_captures_order(): void
    {
        $store = $this->store();
        $store->paymentSettings()->create([
            'provider' => 'paypal',
            'enabled' => true,
            'mode' => 'sandbox',
            'public_key' => 'client-id',
            'secret_key' => 'client-secret',
            'webhook_secret' => 'WH-123',
        ]);

        $appointment = Appointment::create([
            'store_id' => $store->id,
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
            'provider' => 'paypal',
            'provider_payment_id' => 'ORDER-123',
            'status' => 'pending',
            'amount' => 40,
            'currency' => 'USD',
            'payable_type' => $appointment->getMorphClass(),
            'payable_id' => $appointment->id,
        ]);

        Http::fake(function ($request) use ($payment) {
            if (str_contains($request->url(), '/v1/oauth2/token')) {
                return Http::response(['access_token' => 'token'], 200);
            }

            if (str_contains($request->url(), '/v1/notifications/verify-webhook-signature')) {
                return Http::response(['verification_status' => 'SUCCESS'], 200);
            }

            if (str_contains($request->url(), '/v2/checkout/orders')) {
                if ($request->method() === 'GET') {
                    return Http::response([
                        'status' => 'APPROVED',
                        'purchase_units' => [['reference_id' => (string) $payment->id]],
                    ], 200);
                }

                return Http::response(['status' => 'COMPLETED'], 201);
            }

            return Http::response([], 500);
        });

        $payload = [
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'ORDER-123'],
        ];

        $this->postJson("/api/v1/payments/paypal/webhook/{$store->id}", $payload, [
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
            'PAYPAL-CERT-URL' => 'https://api-m.sandbox.paypal.com/certificate',
            'PAYPAL-TRANSMISSION-ID' => 'txn-1',
            'PAYPAL-TRANSMISSION-SIG' => 'sig',
            'PAYPAL-TRANSMISSION-TIME' => '2026-08-17T00:00:00Z',
        ])->assertSuccessful();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);
    }

    public function test_paypal_capture_completed_webhook_resolves_order_id(): void
    {
        $store = $this->store();
        $store->paymentSettings()->create([
            'provider' => 'paypal',
            'enabled' => true,
            'mode' => 'sandbox',
            'public_key' => 'client-id',
            'secret_key' => 'client-secret',
            'webhook_secret' => 'WH-123',
        ]);

        $appointment = Appointment::create([
            'store_id' => $store->id,
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
            'provider' => 'paypal',
            'provider_payment_id' => 'ORDER-123',
            'status' => 'pending',
            'amount' => 40,
            'currency' => 'USD',
            'payable_type' => $appointment->getMorphClass(),
            'payable_id' => $appointment->id,
        ]);

        Http::fake(function ($request) use ($payment) {
            if (str_contains($request->url(), '/v1/oauth2/token')) {
                return Http::response(['access_token' => 'token'], 200);
            }

            if (str_contains($request->url(), '/v1/notifications/verify-webhook-signature')) {
                return Http::response(['verification_status' => 'SUCCESS'], 200);
            }

            if (str_contains($request->url(), '/v2/checkout/orders')) {
                if ($request->method() === 'GET') {
                    return Http::response([
                        'status' => 'COMPLETED',
                        'purchase_units' => [['reference_id' => (string) $payment->id]],
                    ], 200);
                }

                return Http::response(['status' => 'COMPLETED'], 201);
            }

            return Http::response([], 500);
        });

        $payload = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE-1',
                'supplementary_data' => ['related_ids' => ['order_id' => 'ORDER-123']],
            ],
        ];

        $this->postJson("/api/v1/payments/paypal/webhook/{$store->id}", $payload, [
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
            'PAYPAL-CERT-URL' => 'https://api-m.sandbox.paypal.com/certificate',
            'PAYPAL-TRANSMISSION-ID' => 'txn-1',
            'PAYPAL-TRANSMISSION-SIG' => 'sig',
            'PAYPAL-TRANSMISSION-TIME' => '2026-08-17T00:00:00Z',
        ])->assertSuccessful();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
    }

    public function test_provider_priority_prefers_mercadopago(): void
    {
        $store = $this->store();

        foreach (['mercadopago', 'stripe', 'paypal'] as $provider) {
            $store->paymentSettings()->create([
                'provider' => $provider,
                'enabled' => true,
                'mode' => 'sandbox',
                'public_key' => 'pk',
                'secret_key' => 'sk',
                'webhook_secret' => 'wh',
            ]);
        }
        $service = $this->service($store);

        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response([
                'id' => 'pref_123',
                'init_point' => 'https://checkout.mercadopago.com/pref_123',
            ], 201),
        ]);

        $response = $this->postJson('/api/v1/store/appointments', $this->bookingPayload($service), [
            'X-Store-Api-Key' => 'test-key-123',
        ]);

        $response->assertStatus(201);
        $this->assertSame('https://checkout.mercadopago.com/pref_123', $response->json('checkout_url'));
        $this->assertSame('mercadopago', Payment::first()->provider);
    }

    public function test_provider_priority_uses_stripe_when_mercadopago_disabled(): void
    {
        $store = $this->store();

        $store->paymentSettings()->create(['provider' => 'mercadopago', 'enabled' => false, 'mode' => 'sandbox', 'secret_key' => 'sk']);
        $store->paymentSettings()->create(['provider' => 'stripe', 'enabled' => true, 'mode' => 'sandbox', 'secret_key' => 'sk_test']);
        $store->paymentSettings()->create(['provider' => 'paypal', 'enabled' => true, 'mode' => 'sandbox', 'public_key' => 'cid', 'secret_key' => 'csec']);
        $service = $this->service($store);

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_123',
                'url' => 'https://checkout.stripe.com/cs_123',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/store/appointments', $this->bookingPayload($service), [
            'X-Store-Api-Key' => 'test-key-123',
        ]);

        $response->assertStatus(201);
        $this->assertSame('stripe', Payment::first()->provider);
        $this->assertSame('https://checkout.stripe.com/cs_123', $response->json('checkout_url'));
    }
}
