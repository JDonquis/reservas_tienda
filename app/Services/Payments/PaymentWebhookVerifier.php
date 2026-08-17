<?php

namespace App\Services\Payments;

use App\Models\PaymentSetting;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentWebhookVerifier
{
    public function verify(Request $request, Store $store, string $provider): bool
    {
        if ($provider === 'stripe') {
            return $this->verifyStripe($request, $this->setting($store, 'stripe'));
        }

        if ($provider === 'paypal') {
            return $this->verifyPayPal($request, $this->setting($store, 'paypal'));
        }

        return true; // Mercado Pago: sin verificación de firma
    }

    protected function verifyStripe(Request $request, ?PaymentSetting $setting): bool
    {
        if (! $setting || ! $setting->webhook_secret) {
            return false;
        }

        $signature = $request->header('Stripe-Signature');
        $payload = $request->getContent();

        if (! $signature || ! $payload) {
            return false;
        }

        preg_match('/t=(\d+)/', $signature, $time);
        preg_match('/v1=([a-f0-9]+)/', $signature, $v1);

        if (! isset($time[1], $v1[1])) {
            return false;
        }

        $signedPayload = $time[1].'.'.$payload;
        $computed = hash_hmac('sha256', $signedPayload, $setting->webhook_secret);

        return hash_equals($v1[1], $computed);
    }

    protected function verifyPayPal(Request $request, ?PaymentSetting $setting): bool
    {
        if (! $setting || ! $setting->webhook_secret || ! $setting->public_key || ! $setting->secret_key) {
            return false;
        }

        $baseUrl = $setting->mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        $tokenResponse = Http::withBasicAuth($setting->public_key, $setting->secret_key)
            ->asForm()
            ->post($baseUrl.'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if ($tokenResponse->failed()) {
            return false;
        }

        $response = Http::withToken($tokenResponse->json('access_token'))
            ->post($baseUrl.'/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
                'cert_url' => $request->header('PAYPAL-CERT-URL'),
                'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                'webhook_id' => $setting->webhook_secret,
                'webhook_event' => json_decode($request->getContent()),
            ]);

        return $response->successful()
            && $response->json('verification_status') === 'SUCCESS';
    }

    protected function setting(Store $store, string $provider): ?PaymentSetting
    {
        return $store->paymentSettings()
            ->where('provider', $provider)
            ->first();
    }
}
