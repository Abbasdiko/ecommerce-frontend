<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $event,
        public ?Payment $payment = null,
    ) {}

    public function build(): self
    {
        return $this->subject($this->event === 'paid'
            ? "Payment confirmed for order {$this->order->order_number}"
            : "Order {$this->order->order_number} received")
            ->view('emails.orders.status');
    }
}
