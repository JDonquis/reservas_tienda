<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalGateway implements PaymentGateway
{
    public function createCheckout(Store $store, Payment $payment, string $description): ?string
    {
        $setting = $this->setting($store);

        if (! $setting || ! $setting->public_key || ! $setting->secret_key) {
            return null;
        }

        $token = $this->accessToken($setting);

        if (! $token) {
            return null;
        }

        $response = Http::withToken($token)
            ->post($this->baseUrl($setting).'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => (string) $payment->id,
                    'custom_id' => (string) $payment->id,
                    'description' => $description,
                    'amount' => [
                        'currency_code' => $payment->currency,
                        'value' => number_format((float) $payment->amount, 2, '.', ''),
                    ],
                ]],
                'application_context' => [
                    'brand_name' => $store->name,
                    'return_url' => config('app.frontend_url'),
                    'cancel_url' => config('app.frontend_url'),
                    'user_action' => 'PAY_NOW',
                ],
            ]);

        if ($response->failed()) {
            Log::error('PayPal order error: '.$response->body());

            return null;
        }

        $data = $response->json();

        $payment->update(['provider_payment_id' => $data['id'] ?? null]);

        foreach ($data['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                return $link['href'] ?? null;
            }
        }

        return null;
    }

    public function resolvePayment(Store $store, string $providerPaymentId): ?array
    {
        $setting = $this->setting($store);

        if (! $setting || ! $setting->public_key || ! $setting->secret_key) {
            return null;
        }

        $token = $this->accessToken($setting);

        if (! $token) {
            return null;
        }

        $response = Http::withToken($token)
            ->get($this->baseUrl($setting).'/v2/checkout/orders/'.$providerPaymentId);

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();
        $status = $data['status'] ?? null;

        if ($status === 'APPROVED') {
            $capture = Http::withToken($token)
                ->post($this->baseUrl($setting).'/v2/checkout/orders/'.$providerPaymentId.'/capture');

            if ($capture->successful()) {
                $status = 'COMPLETED';
            }
        }

        return [
            'status' => $status === 'COMPLETED' ? 'approved' : $status,
            'external_reference' => $data['purchase_units'][0]['reference_id'] ?? null,
        ];
    }

    protected function setting(Store $store): ?PaymentSetting
    {
        return $store->paymentSettings()
            ->where('provider', 'paypal')
            ->first();
    }

    protected function baseUrl(PaymentSetting $setting): string
    {
        return $setting->mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    protected function accessToken(PaymentSetting $setting): ?string
    {
        $response = Http::withBasicAuth($setting->public_key, $setting->secret_key)
            ->asForm()
            ->post($this->baseUrl($setting).'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            Log::error('PayPal token error: '.$response->body());

            return null;
        }

        return $response->json('access_token');
    }
}
