<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmationMail;
use App\Mail\OrderReceivedMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function store(bool $withOwner = false, string $key = 'test-key-123'): Store
    {
        $data = ['name' => 'Tienda', 'api_key' => $key];

        if ($withOwner) {
            $owner = User::factory()->create(['role' => 'store_owner']);
            $data['user_id'] = $owner->id;
        }

        return Store::create($data);
    }

    private function product(Store $store, int $price = 25): Product
    {
        return Product::create([
            'store_id' => $store->id,
            'name' => 'Producto',
            'price' => $price,
            'image_path' => 'products/example.jpg',
            'is_active' => true,
        ]);
    }

    private function orderPayload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Maria Gomez',
            'customer_email' => 'maria@example.com',
            'customer_phone' => '555-1234',
            'shipping_method' => 'pickup',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ], $overrides);
    }

    public function test_create_order_with_stripe_returns_checkout_url(): void
    {
        $store = $this->store();
        $store->paymentSettings()->create([
            'provider' => 'stripe',
            'enabled' => true,
            'mode' => 'sandbox',
            'secret_key' => 'sk_test',
            'webhook_secret' => 'whsec_test',
        ]);
        $product = $this->product($store);

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_123',
                'url' => 'https://checkout.stripe.com/cs_123',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/store/orders', $this->orderPayload($product), [
            'X-Store-Api-Key' => 'test-key-123',
        ]);

        $response->assertStatus(201);
        $this->assertSame('https://checkout.stripe.com/cs_123', $response->json('checkout_url'));

        $order = Order::first();
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame(50.0, $order->total);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame(2, $order->items()->first()->quantity);
        $this->assertSame(50.0, $order->items()->first()->line_total);
        $this->assertSame('stripe', Payment::first()->provider);
        $this->assertSame('cs_123', Payment::first()->provider_payment_id);
    }

    public function test_create_order_without_payment_confirms_and_emails(): void
    {
        $store = $this->store(withOwner: true);
        $product = $this->product($store);

        Mail::fake();

        $response = $this->postJson('/api/v1/store/orders', $this->orderPayload($product), [
            'X-Store-Api-Key' => 'test-key-123',
        ]);

        $response->assertStatus(201);
        $this->assertNull($response->json('checkout_url'));

        $order = Order::first();
        $this->assertSame('confirmed', $order->status);
        $this->assertSame('free', $order->payment_status);

        Mail::assertSent(OrderReceivedMail::class, fn ($mail) => $mail->order->id === $order->id);
        Mail::assertSent(OrderConfirmationMail::class, fn ($mail) => $mail->order->id === $order->id);
    }

    public function test_stripe_webhook_marks_order_paid_and_emails(): void
    {
        $store = $this->store(withOwner: true);
        $store->paymentSettings()->create([
            'provider' => 'stripe',
            'enabled' => true,
            'mode' => 'sandbox',
            'secret_key' => 'sk_test',
            'webhook_secret' => 'whsec_test',
        ]);
        $product = $this->product($store);

        $order = Order::create([
            'store_id' => $store->id,
            'customer_name' => 'Maria Gomez',
            'customer_email' => 'maria@example.com',
            'customer_phone' => '555-1234',
            'shipping_method' => 'delivery',
            'shipping_address' => 'Calle 1 #23',
            'subtotal' => 50,
            'total' => 50,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 25,
            'quantity' => 2,
            'line_total' => 50,
        ]);

        $payment = Payment::create([
            'store_id' => $store->id,
            'provider' => 'stripe',
            'provider_payment_id' => 'cs_123',
            'status' => 'pending',
            'amount' => 50,
            'currency' => 'USD',
            'payable_type' => $order->getMorphClass(),
            'payable_id' => $order->id,
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

        Mail::fake();

        $this->postJson("/api/v1/payments/stripe/webhook/{$store->id}", $payload, [
            'Stripe-Signature' => "t={$timestamp},v1={$signature}",
        ])->assertSuccessful();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        Mail::assertSent(OrderReceivedMail::class, fn ($mail) => $mail->order->id === $order->id);
        Mail::assertSent(OrderConfirmationMail::class, fn ($mail) => $mail->order->id === $order->id);
    }

    public function test_create_order_rejects_product_from_another_store(): void
    {
        $store = $this->store();
        $otherStore = $this->store(key: 'other-key-123');
        $product = $this->product($otherStore);

        $response = $this->postJson('/api/v1/store/orders', $this->orderPayload($product), [
            'X-Store-Api-Key' => 'test-key-123',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_create_order_delivery_requires_address(): void
    {
        $store = $this->store();
        $product = $this->product($store);

        $response = $this->postJson('/api/v1/store/orders', $this->orderPayload($product, [
            'shipping_method' => 'delivery',
        ]), [
            'X-Store-Api-Key' => 'test-key-123',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_admin_can_list_show_and_cancel_orders(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        Sanctum::actingAs($admin);

        $store = $this->store();
        $product = $this->product($store);

        $order = Order::create([
            'store_id' => $store->id,
            'customer_name' => 'Maria Gomez',
            'customer_email' => 'maria@example.com',
            'customer_phone' => '555-1234',
            'shipping_method' => 'pickup',
            'subtotal' => 25,
            'total' => 25,
            'status' => 'confirmed',
            'payment_status' => 'free',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 25,
            'quantity' => 1,
            'line_total' => 25,
        ]);

        $this->getJson("/api/v1/admin/stores/{$store->id}/orders")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/v1/admin/stores/{$store->id}/orders/{$order->id}")
            ->assertSuccessful()
            ->assertJsonPath('data.total', 25);

        $this->postJson("/api/v1/admin/stores/{$store->id}/orders/{$order->id}/cancel")
            ->assertSuccessful();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }
}
