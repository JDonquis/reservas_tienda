<?php

namespace App\Services;

use App\Mail\AppointmentConfirmationMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderReceivedMail;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Services\Payments\PaymentGatewayResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentService
{
    public function __construct(
        protected PaymentGatewayResolver $resolver,
        protected GoogleCalendarService $calendarService,
    ) {}

    public function startPayment(Store $store, $payable, float $amount, string $currency, string $description, string $provider): ?string
    {
        $payment = Payment::create([
            'store_id' => $store->id,
            'provider' => $provider,
            'status' => 'pending',
            'amount' => $amount,
            'currency' => $currency,
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->id,
        ]);

        return $this->resolver->for($provider)->createCheckout($store, $payment, $description);
    }

    public function resolveWebhook(Store $store, string $providerPaymentId, string $provider): void
    {
        $info = $this->resolver->for($provider)->resolvePayment($store, $providerPaymentId);

        if (! $info || ! isset($info['external_reference'])) {
            return;
        }

        $payment = Payment::find($info['external_reference']);

        if (! $payment || $payment->store_id !== $store->id) {
            return;
        }

        if ($info['status'] === 'approved') {
            $this->markPaid($payment);
        } elseif (in_array($info['status'], ['rejected', 'cancelled', 'voided', 'unpaid'])) {
            $payment->update(['status' => 'failed']);
        }
    }

    public function markPaid(Payment $payment): void
    {
        if ($payment->status === 'paid') {
            return;
        }

        $payment->update(['status' => 'paid']);

        $payable = $payment->payable;

        if ($payable instanceof Appointment) {
            $this->finalizeAppointment($payment->store, $payable);
        }

        if ($payable instanceof Order) {
            $this->finalizeOrder($payment->store, $payable);
        }
    }

    public function sendOrderEmails(Store $store, Order $order): void
    {
        $owner = $store->owner;

        try {
            if ($owner) {
                Mail::to($owner->email)->send(new OrderReceivedMail($store, $order));
            }
        } catch (\Exception $e) {
            Log::error('Error enviando pedido al dueño: '.$e->getMessage());
        }

        try {
            Mail::to($order->customer_email)->send(new OrderConfirmationMail($store, $order));
        } catch (\Exception $e) {
            Log::error('Error enviando confirmación al cliente: '.$e->getMessage());
        }
    }

    protected function finalizeOrder(Store $store, Order $order): void
    {
        $order->update([
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->sendOrderEmails($store, $order->load('items'));
    }

    protected function finalizeAppointment(Store $store, Appointment $appointment): void
    {
        $appointment->update([
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        try {
            $event = $this->calendarService->createAppointmentEvent($store, [
                'customer_name' => $appointment->customer_name,
                'customer_email' => $appointment->customer_email,
                'customer_phone' => $appointment->customer_phone,
                'start_time' => $appointment->start_time,
                'end_time' => $appointment->end_time,
                'service_name' => $appointment->service?->name,
            ]);

            if ($event) {
                $appointment->update(['google_event_id' => $event->id]);
            }
        } catch (\Exception $e) {
            Log::error('Error creando evento en Google Calendar: '.$e->getMessage());
        }

        try {
            Mail::to($appointment->customer_email)
                ->send(new AppointmentConfirmationMail($appointment->load('service')));
        } catch (\Exception $e) {
            Log::error('Error enviando correo de confirmación: '.$e->getMessage());
        }
    }
}
