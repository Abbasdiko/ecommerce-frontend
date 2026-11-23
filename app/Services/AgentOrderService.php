<?php

namespace App\Services;

use App\Models\AgentOrder;
use App\Models\Commission;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AgentOrderService
{
    public const FLOW = [
        'pending' => ['processing', 'cancelled'],
        'processing' => ['ready_for_dispatch', 'cancelled'],
        'ready_for_dispatch' => ['dispatched'],
        'dispatched' => ['out_for_delivery', 'delivered'],
        'out_for_delivery' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function __construct(private WalletService $wallets)
    {
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::FLOW[$from] ?? [], true);
    }

    public function transition(AgentOrder $agentOrder, string $to, ?string $tracking = null, ?string $note = null): AgentOrder
    {
        if (! $this->canTransition($agentOrder->status, $to)) {
            throw new \RuntimeException("Cannot move sub-order from {$agentOrder->status} to {$to}.");
        }

        return DB::transaction(function () use ($agentOrder, $to, $tracking, $note) {
            $agentOrder->status = $to;
            if ($tracking) {
                $agentOrder->tracking_number = $tracking;
            }
            if ($to === 'delivered') {
                $agentOrder->delivered_at = now();
            }
            $agentOrder->save();

            OrderStatusHistory::create([
                'order_id' => $agentOrder->order_id,
                'agent_order_id' => $agentOrder->id,
                'status' => $to,
                'note' => $note,
                'changed_by' => Auth::id(),
            ]);

            if ($to === 'delivered') {
                $this->onDelivered($agentOrder);
            }
            if ($to === 'cancelled') {
                Commission::where('agent_order_id', $agentOrder->id)->update(['status' => 'reversed']);
            }

            $this->syncParentOrder($agentOrder);

            return $agentOrder;
        });
    }

    private function onDelivered(AgentOrder $agentOrder): void
    {
        // Finalize commission rows and credit the agent's pending wallet balance (spec §60, §64).
        Commission::where('agent_order_id', $agentOrder->id)
            ->where('status', 'pending')
            ->update(['status' => 'finalized']);

        $agent = $agentOrder->agent;
        if ($agent && (float) $agentOrder->agent_earning > 0) {
            $wallet = $this->wallets->walletFor($agent->user, 'agent');
            $wallet->refresh();
            $alreadyCredited = $wallet->transactions()
                ->where('reference_type', 'AgentOrder')
                ->where('reference_id', $agentOrder->id)
                ->exists();

            if (! $alreadyCredited) {
                $this->wallets->creditPending(
                    $wallet,
                    (float) $agentOrder->agent_earning,
                    'sale',
                    "Sale earnings — {$agentOrder->reference}",
                    $agentOrder,
                );
            }
        }
    }

    private function syncParentOrder(AgentOrder $agentOrder): void
    {
        $order = $agentOrder->order()->with('agentOrders')->first();
        if (! $order) {
            return;
        }

        $statuses = $order->agentOrders->pluck('status')->unique();

        if ($statuses->count() === 1) {
            $only = $statuses->first();
            if (in_array($only, ['delivered', 'cancelled'], true) && $order->order_status !== $only) {
                $order->update(['order_status' => $only]);
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status' => $only,
                    'note' => 'All sub-orders ' . $only,
                    'changed_by' => Auth::id(),
                ]);
            }
        } elseif ($order->order_status === 'pending' || $order->order_status === 'confirmed') {
            $order->update(['order_status' => 'processing']);
        }
    }
}
