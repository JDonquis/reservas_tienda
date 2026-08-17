<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeGateway implements PaymentGateway
{
    protected string $baseUrl = 'https://api.stripe.com/v1';

    public function createCheckout(Store $store, Payment $payment, string $description): ?string
    {
        $setting = $this->setting($store);

        if (! $setting || ! $setting->secret_key) {
            return null;
        }

        $response = Http::withToken($setting->secret_key)
            ->asForm()
            ->post($this->baseUrl.'/checkout/sessions', [
                'mode' => 'payment',
                'client_reference_id' => (string) $payment->id,
                'success_url' => config('app.frontend_url'),
                'cancel_url' => config('app.frontend_url'),
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => $payment->currency,
                'line_items[0][price_data][product_data][name]' => $description,
                'line_items[0][price_data][unit_amount]' => (int) round((float) $payment->amount * 100),
            ]);

        if ($response->failed()) {
            Log::error('Stripe session error: '.$response->body());

            return null;
        }

        $data = $response->json();

        $payment->update(['provider_payment_id' => $data['id'] ?? null]);

        return $data['url'] ?? null;
    }

    public function resolvePayment(Store $store, string $providerPaymentId): ?array
    {
        $setting = $this->setting($store);

        if (! $setting || ! $setting->secret_key) {
            return null;
        }

        $response = Http::withToken($setting->secret_key)
            ->get($this->baseUrl.'/checkout/sessions/'.$providerPaymentId);

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();
        $paymentStatus = $data['payment_status'] ?? null;

        return [
            'status' => $paymentStatus === 'paid' ? 'approved' : $paymentStatus,
            'external_reference' => $data['client_reference_id'] ?? null,
        ];
    }

    protected function setting(Store $store): ?PaymentSetting
    {
        return $store->paymentSettings()
            ->where('provider', 'stripe')
            ->first();
    }
}
