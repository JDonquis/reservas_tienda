<?php

namespace App\Contracts\Payments;

use App\Models\Payment;
use App\Models\Store;

interface PaymentGateway
{
    /**
     * Crea un checkout en la pasarela y devuelve la URL de pago (o null si falla).
     */
    public function createCheckout(Store $store, Payment $payment, string $description): ?string;

    /**
     * Consulta el estado de un pago en la pasarela.
     *
     * @return array{status: ?string, external_reference: ?string}|null
     */
    public function resolvePayment(Store $store, string $providerPaymentId): ?array;
}
