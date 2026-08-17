<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Models\Store;
use InvalidArgumentException;

class PaymentGatewayResolver
{
    protected array $gateways = [
        'mercadopago' => MercadoPagoGateway::class,
        'stripe' => StripeGateway::class,
        'paypal' => PayPalGateway::class,
    ];

    public function for(string $provider): PaymentGateway
    {
        $class = $this->gateways[$provider]
            ?? throw new InvalidArgumentException("Proveedor de pago no soportado: {$provider}");

        return app($class);
    }

    public function activeProviderFor(Store $store): ?string
    {
        $enabled = $store->paymentSettings()
            ->where('enabled', true)
            ->pluck('provider')
            ->all();

        foreach (array_keys($this->gateways) as $provider) {
            if (in_array($provider, $enabled, true)) {
                return $provider;
            }
        }

        return null;
    }
}
