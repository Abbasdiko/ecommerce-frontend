<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\Order;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Processes an approved refund (spec §66, §106): reverse commission, adjust the
 * agent wallet, credit the customer wallet, and update the order payment status.
 */
class RefundService
{
    public function __construct(private WalletService $wallets)
    {
    }

    public function approve(ReturnRequest $request, float $amount, ?string $adminNote = null): Refund
    {
        return DB::transaction(function () use ($request, $amount, $adminNote) {
            $order = $request->order()->with('agentOrders')->firstOrFail();
            $item = $request->order_item_id ? $request->orderItem : null;

            // ---- reverse commission for the affected scope ----
            $commissionQuery = Commission::where('order_id', $order->id);
            if ($item) {
                $commissionQuery->where('order_item_id', $item->id);
            }
            $reversed = 0.0;
            $agentEarningClaw = 0.0;
            foreach ($commissionQuery->where('status', '!=', 'reversed')->get() as $commission) {
                $commission->update(['status' => 'reversed']);
                $reversed += (float) $commission->commission_amount;
                $agentEarningClaw += (float) $commission->agent_earning;
            }

            // ---- claw back agent earnings if already in the wallet ----
            if ($item && $agentEarningClaw > 0) {
                $this->clawbackAgent($item->agent_id, $agentEarningClaw, $order->order_number);
            } elseif (! $item) {
                foreach ($order->agentOrders as $ao) {
                    if ((float) $ao->agent_earning > 0) {
                        $this->clawbackAgent($ao->agent_id, (float) $ao->agent_earning, $order->order_number);
                    }
                }
            }

            // ---- credit the customer wallet ----
            $customer = User::find($request->customer_id);
            $wallet = $this->wallets->walletFor($customer, 'customer');
            $this->wallets->creditAvailable(
                $wallet, $amount, 'refund_credit',
                "Refund for order {$order->order_number}", $request,
            );

            $refund = Refund::create([
                'return_request_id' => $request->id,
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'amount' => $amount,
                'method' => 'wallet',
                'status' => 'completed',
                'commission_reversed' => $reversed > 0,
                'processed_by' => Auth::id(),
                'processed_at' => now(),
            ]);

            $request->update([
                'status' => 'completed',
                'amount_approved' => $amount,
                'admin_note' => $adminNote,
                'resolved_by' => Auth::id(),
                'resolved_at' => now(),
            ]);

            $totalRefunded = (float) $order->refunds()->sum('amount') + $amount;
            $order->update([
                'payment_status' => $totalRefunded >= (float) $order->total ? 'refunded' : 'partially_refunded',
                'order_status' => $item ? $order->order_status : 'returned',
            ]);

            return $refund;
        });
    }

    private function clawbackAgent(int $agentId, float $amount, string $orderNumber): void
    {
        $agentUser = \App\Models\Agent::with('user')->find($agentId)?->user;
        if (! $agentUser) {
            return;
        }

        $wallet = Wallet::whereKey($this->wallets->walletFor($agentUser, 'agent')->id)->lockForUpdate()->first();

        // Take from pending first, then available.
        $fromPending = min($amount, (float) $wallet->pending_balance);
        $fromAvailable = $amount - $fromPending;

        foreach ([['pending', $fromPending], ['available', $fromAvailable]] as [$bucket, $take]) {
            if ($take <= 0) {
                continue;
            }
            $column = $bucket . '_balance';
            $before = (float) $wallet->{$column};
            $wallet->{$column} = $before - $take;
            $wallet->save();

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'refund',
                'reference_type' => 'Order',
                'reference_id' => null,
                'amount' => $take,
                'direction' => 'debit',
                'balance_before' => $before,
                'balance_after' => $before - $take,
                'bucket' => $bucket,
                'description' => "Refund clawback — order {$orderNumber}",
                'status' => 'completed',
            ]);
        }
    }

    public function reject(ReturnRequest $request, string $reason): ReturnRequest
    {
        $request->update([
            'status' => 'rejected',
            'admin_note' => $reason,
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        return $request;
    }
}
