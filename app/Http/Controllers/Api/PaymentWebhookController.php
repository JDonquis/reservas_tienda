<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Payments\PaymentWebhookVerifier;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function mercadopago(Request $request, Store $store, PaymentService $service)
    {
        $paymentId = $request->input('data.id')
            ?? $request->input('id')
            ?? $request->input('data_id');

        if (! $paymentId) {
            return response()->json(['message' => 'ok']);
        }

        $service->resolveWebhook($store, (string) $paymentId, 'mercadopago');

        return response()->json(['message' => 'ok']);
    }

    public function stripe(Request $request, Store $store, PaymentService $service, PaymentWebhookVerifier $verifier)
    {
        if (! $verifier->verify($request, $store, 'stripe')) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $sessionId = $request->input('data.object.id');

        if (! $sessionId) {
            return response()->json(['message' => 'ok']);
        }

        $service->resolveWebhook($store, (string) $sessionId, 'stripe');

        return response()->json(['message' => 'ok']);
    }

    public function paypal(Request $request, Store $store, PaymentService $service, PaymentWebhookVerifier $verifier)
    {
        if (! $verifier->verify($request, $store, 'paypal')) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $eventType = $request->input('event_type');
        $resource = $request->input('resource', []);

        $orderId = $resource['id'] ?? null;

        if (str_starts_with((string) $eventType, 'PAYMENT.')) {
            $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? $orderId;
        }

        if (! $orderId) {
            return response()->json(['message' => 'ok']);
        }

        $service->resolveWebhook($store, (string) $orderId, 'paypal');

        return response()->json(['message' => 'ok']);
    }
}
