<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\Auth;

/**
 * Order state machine (spec §96). Guards invalid transitions and records history.
 */
class OrderWorkflow
{
    public const FLOW = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['ready_for_dispatch', 'cancelled'],
        'ready_for_dispatch' => ['dispatched'],
        'dispatched' => ['out_for_delivery'],
        'out_for_delivery' => ['delivered'],
        'delivered' => ['returned'],
        'cancelled' => [],
        'returned' => [],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::FLOW[$from] ?? [], true);
    }

    public function transition(Order $order, string $to, ?string $note = null): Order
    {
        if (! $this->canTransition($order->order_status, $to)) {
            throw new \RuntimeException("Cannot move order from {$order->order_status} to {$to}.");
        }

        $order->update(['order_status' => $to]);
        $this->recordHistory($order, $to, $note);

        return $order;
    }

    public function recordHistory(Order $order, string $status, ?string $note = null): void
    {
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $status,
            'note' => $note,
            'changed_by' => Auth::id(),
        ]);
    }
}
