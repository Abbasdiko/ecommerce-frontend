<?php

namespace App\Services;

use App\Mail\OrderStatusMail;
use App\Models\AgentOrder;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OrderNotificationService
{
    public function orderPlaced(Order $order): void
    {
        if ($order->payment_status === 'successful') {
            return;
        }

        $this->notify($order, 'placed');
    }

    public function paymentConfirmed(Order $order): void
    {
        $this->notify($order, 'paid');
    }

    private function notify(Order $order, string $event): void
    {
        $order->loadMissing(['customer', 'items', 'payments']);
        $emails = [
            $order->customer?->email,
            PlatformSettings::value('platform.support_email'),
        ];

        $agentOrders = AgentOrder::query()
            ->where('order_id', $order->id)
            ->with('agent.user')
            ->get();

        foreach ($agentOrders as $agentOrder) {
            $emails[] = $agentOrder->agent?->email;
            $emails[] = $agentOrder->agent?->user?->email;
        }

        foreach (array_unique(array_filter($emails, fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL))) as $email) {
            try {
                Mail::to($email)->send(new OrderStatusMail(
                    $order,
                    $event,
                    $order->payments->sortByDesc('created_at')->first(),
                ));
            } catch (Throwable $exception) {
                Log::error('Order notification email could not be sent.', [
                    'order_id' => $order->id,
                    'event' => $event,
                    'recipient' => $email,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }
}
