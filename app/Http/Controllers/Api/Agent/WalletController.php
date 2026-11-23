<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\WalletService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    use ApiResponse;

    public function __construct(private WalletService $wallets)
    {
    }

    public function show(Request $request)
    {
        $agent = $request->user()->agent;
        abort_unless($agent, 403, 'Complete your business profile first.');

        $wallet = $this->wallets->walletFor($request->user(), 'agent');

        $transactions = $wallet->transactions()
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->paginate($this->perPage($request));

        return $this->ok([
            'wallet' => [
                'pending_balance' => (float) $wallet->pending_balance,
                'available_balance' => (float) $wallet->available_balance,
                'withdrawn_balance' => (float) $wallet->withdrawn_balance,
                'currency' => $wallet->currency,
            ],
            'transactions' => $transactions,
            'payouts' => Withdrawal::where('agent_id', $agent->id)
                ->latest()
                ->paginate($this->perPage($request, 10)),
            'sales' => $agent->agentOrders()
                ->whereHas('order', fn ($q) => $q->where('payment_status', 'successful'))
                ->with('order:id,order_number,payment_status,created_at')
                ->withCount('items')
                ->latest()
                ->paginate($this->perPage($request, 10)),
            'sales_summary' => [
                'sales_total' => (float) $agent->agentOrders()
                    ->whereHas('order', fn ($q) => $q->where('payment_status', 'successful'))->sum('total'),
                'company_earnings' => (float) \App\Models\Commission::where('agent_id', $agent->id)
                    ->whereIn('status', ['pending', 'finalized'])
                    ->whereHas('order', fn ($q) => $q->where('payment_status', 'successful'))
                    ->sum('commission_amount'),
                'vendor_earnings' => (float) $agent->agentOrders()
                    ->whereHas('order', fn ($q) => $q->where('payment_status', 'successful'))->sum('agent_earning'),
            ],
        ]);
    }
}
