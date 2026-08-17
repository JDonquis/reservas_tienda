<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
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

        $service->resolveWebhook($store, (string) $paymentId);

        return response()->json(['message' => 'ok']);
    }
}
