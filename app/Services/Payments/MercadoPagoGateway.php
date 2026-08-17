<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Models\Payment;
use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoPagoGateway implements PaymentGateway
{
    protected string $baseUrl = 'https://api.mercadopago.com';

    public function createCheckout(Store $store, Payment $payment, string $description): ?string
    {
        $setting = $store->paymentSettings()
            ->where('provider', 'mercadopago')
            ->where('enabled', true)
            ->first();

        if (! $setting || ! $setting->secret_key) {
            return null;
        }

        $response = Http::withToken($setting->secret_key)
            ->post($this->baseUrl.'/checkout/preferences', [
                'items' => [[
                    'title' => $description,
                    'quantity' => 1,
                    'unit_price' => (float) $payment->amount,
                    'currency_id' => $payment->currency,
                ]],
                'external_reference' => (string) $payment->id,
                'notification_url' => route('payments.mercadopago.webhook', ['store' => $store->id]),
                'auto_return' => 'approved',
                'back_urls' => [
                    'success' => config('app.frontend_url'),
                    'failure' => config('app.frontend_url'),
                    'pending' => config('app.frontend_url'),
                ],
            ]);

        if ($response->failed()) {
            Log::error('MercadoPago preference error: '.$response->body());

            return null;
        }

        $data = $response->json();

        $payment->update(['provider_payment_id' => $data['id'] ?? null]);

        return $data['init_point'] ?? null;
    }

    public function resolvePayment(Store $store, string $providerPaymentId): ?array
    {
        $setting = $store->paymentSettings()
            ->where('provider', 'mercadopago')
            ->first();

        if (! $setting || ! $setting->secret_key) {
            return null;
        }

        $response = Http::withToken($setting->secret_key)
            ->get($this->baseUrl.'/v1/payments/'.$providerPaymentId);

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();

        return [
            'status' => $data['status'] ?? null,
            'external_reference' => $data['external_reference'] ?? null,
        ];
    }
}
