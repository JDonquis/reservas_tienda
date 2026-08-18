<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Store $store,
        public Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nuevo pedido #'.$this->order->id.' - '.$this->store->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-received',
        );
    }
}
