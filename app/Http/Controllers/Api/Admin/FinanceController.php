<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Settlement;
use App\Models\Agent;
use App\Models\AgentOrder;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\PaystackTransferService;
use App\Services\AuditLogger;
use App\Services\WalletService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    use ApiResponse;

    public function __construct(private WalletService $wallets)
    {
    }

    /** Review agent balances (spec §6 finance duties). */
    public function agentBalances(Request $request)
    {
        $this->authorizePermission($request, 'wallet.view');

        $wallets = Wallet::where('owner_type', 'agent')
            ->with('user.agent:id,user_id,business_name')
            ->when($request->search, fn ($q, $s) => $q->whereHas('user.agent', fn ($q) => $q->where('business_name', 'like', "%$s%")))
            ->orderByDesc('available_balance')
            ->paginate($this->perPage($request));

        $wallets->getCollection()->transform(fn ($w) => [
            'wallet_id' => $w->id,
            'agent_id' => optional($w->user->agent)->id,
            'business_name' => optional($w->user->agent)->business_name,
            'pending_balance' => (float) $w->pending_balance,
            'available_balance' => (float) $w->available_balance,
            'withdrawn_balance' => (float) $w->withdrawn_balance,
            'sales_total' => (float) AgentOrder::where('agent_id', optional($w->user->agent)->id)
                ->whereHas('order', fn ($q) => $q->where('payment_status', 'successful'))->sum('total'),
            'company_earnings' => (float) \App\Models\Commission::where('agent_id', optional($w->user->agent)->id)
                ->whereIn('status', ['pending', 'finalized'])
                ->whereHas('order', fn ($q) => $q->where('payment_status', 'successful'))
                ->sum('commission_amount'),
        ]);

        return $this->ok([
            'totals' => [
                'pending' => (float) Wallet::where('owner_type', 'agent')->sum('pending_balance'),
                'available' => (float) Wallet::where('owner_type', 'agent')->sum('available_balance'),
                'withdrawn' => (float) Wallet::where('owner_type', 'agent')->sum('withdrawn_balance'),
            ],
            'wallets' => $wallets,
        ]);
    }

    public function settlements(Request $request)
    {
        $this->authorizePermission($request, 'settlement.view');

        $settlements = Settlement::query()
            ->with(['user:id,first_name,last_name', 'bankAccount'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($this->perPage($request));

        return $this->ok($settlements);
    }

    public function transitionSettlement(Request $request, Settlement $settlement)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'processing', 'completed', 'rejected'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $this->authorizePermission($request, $data['status'] === 'rejected' ? 'settlement.reject' : 'settlement.approve');

        if (in_array($settlement->status, ['completed', 'rejected'], true)) {
            return $this->fail('This settlement is already finalised.', null, 409);
        }

        DB::transaction(function () use ($settlement, $data, $request) {
            if ($data['status'] === 'rejected') {
                $wallet = Wallet::find($settlement->wallet_id);
                if ($wallet) {
                    $this->wallets->creditAvailable(
                        $wallet, (float) $settlement->amount, 'adjustment',
                        "Settlement {$settlement->reference} rejected — funds returned", $settlement,
                    );
                    $wallet->decrement('withdrawn_balance', min((float) $wallet->withdrawn_balance, (float) $settlement->amount));
                }
            }
            $settlement->update([
                'status' => $data['status'],
                'note' => $data['note'] ?? $settlement->note,
                'processed_by' => $request->user()->id,
                'processed_at' => in_array($data['status'], ['completed', 'rejected'], true) ? now() : null,
            ]);
        });

        AuditLogger::record('settlement.' . $data['status'], $settlement);

        return $this->ok($settlement->fresh(), 'Settlement ' . $data['status']);
    }

    public function companyWallet(Request $request)
    {
        $this->authorizePermission($request, 'wallet.view');

        $paidSales = Order::where('payment_status', 'successful')->sum('total');
        $refunds = Refund::where('status', 'completed')->sum('amount');
        $commission = \App\Models\Commission::whereIn('status', ['pending', 'finalized'])
            ->whereHas('order', fn ($q) => $q->where('payment_status', 'successful'));
        $vendorPaid = (float) Wallet::where('owner_type', 'agent')->sum('withdrawn_balance');

        return $this->ok([
            'gross_sales' => (float) $paidSales,
            'refunds' => (float) $refunds,
            'company_balance_estimate' => (float) $paidSales - (float) $refunds - $vendorPaid,
            'company_earnings' => (float) (clone $commission)->sum('commission_amount'),
            'pending_company_earnings' => (float) (clone $commission)->where('status', 'pending')->sum('commission_amount'),
            'finalized_company_earnings' => (float) (clone $commission)->where('status', 'finalized')->sum('commission_amount'),
            'vendor_pending' => (float) Wallet::where('owner_type', 'agent')->sum('pending_balance'),
            'vendor_available' => (float) Wallet::where('owner_type', 'agent')->sum('available_balance'),
            'vendor_payouts_pending' => (float) Withdrawal::whereIn('status', ['pending', 'approved', 'processing'])->sum('amount'),
            'vendor_withdrawn' => (float) Wallet::where('owner_type', 'agent')->sum('withdrawn_balance'),
            'recent_sales' => AgentOrder::whereHas('order', fn ($q) => $q->where('payment_status', 'successful'))
                ->with(['agent:id,business_name', 'order:id,order_number,placed_at'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn ($sale) => [
                    'order_number' => $sale->order->order_number,
                    'agent' => $sale->agent->business_name,
                    'amount' => (float) $sale->total,
                    'company_earned' => (float) $sale->commission,
                    'vendor_earned' => (float) $sale->agent_earning,
                    'date' => $sale->created_at,
                ]),
            'recent_payouts' => Withdrawal::with('agent:id,business_name')
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn ($payout) => [
                    'reference' => $payout->reference,
                    'agent' => $payout->agent->business_name,
                    'amount' => (float) $payout->net_amount,
                    'method' => $payout->payout_method,
                    'status' => $payout->status,
                    'date' => $payout->created_at,
                ]),
            'note' => 'The balance estimate reflects recorded successful sales and refunds; verify the actual available balance with your payment provider.',
        ]);
    }

    public function payout(Request $request, Agent $agent, PaystackTransferService $paystack)
    {
        $this->authorizePermission($request, 'withdrawal.approve');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
            'method' => ['required', Rule::in(['manual', 'paystack'])],
            'bank_account_id' => ['nullable', 'integer'],
        ]);

        $accountQuery = $agent->user->bankAccounts();
        $account = isset($data['bank_account_id'])
            ? $accountQuery->whereKey($data['bank_account_id'])->first()
            : ($accountQuery->where('is_default', true)->first() ?? $accountQuery->first());
        if (! $account) {
            return $this->fail('The vendor must save a default payout bank account first.', null, 422);
        }

        $wallet = $this->wallets->walletFor($agent->user, 'agent');
        if ((float) $data['amount'] > (float) $wallet->available_balance) {
            return $this->fail('Payout amount exceeds the vendor available balance.', null, 422);
        }

        $withdrawal = DB::transaction(function () use ($agent, $account, $data, $wallet) {
            $lockedWallet = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            if ((float) $data['amount'] > (float) $lockedWallet->available_balance) {
                return null;
            }

            $withdrawal = Withdrawal::create([
                'agent_id' => $agent->id,
                'wallet_id' => $lockedWallet->id,
                'bank_account_id' => $account->id,
                'amount' => $data['amount'],
                'fee' => 0,
                'net_amount' => $data['amount'],
                'bank_name' => $account->bank_name,
                'bank_code' => $account->bank_code,
                'account_name' => $account->account_name,
                'account_number' => $account->getAttributes()['account_number'],
                'status' => 'processing',
                'payout_method' => $data['method'],
                'note' => $data['method'] === 'manual' ? 'Manual transfer required.' : 'Paystack transfer initiation requested.',
            ]);

            $this->wallets->debitForSettlement(
                $lockedWallet,
                (float) $data['amount'],
                "Vendor payout {$withdrawal->reference}",
                $withdrawal,
            );

            return $withdrawal;
        });

        if (! $withdrawal) {
            return $this->fail('Payout amount exceeds the vendor available balance.', null, 422);
        }

        if ($data['method'] === 'paystack') {
            try {
                $paystack->initiate($withdrawal);
                $withdrawal->update(['processed_by' => $request->user()->id]);
            } catch (RequestException|ConnectionException|RuntimeException $e) {
                $withdrawal->update(['note' => 'Paystack payout initiation failed: ' . $e->getMessage()]);

                return $this->fail('Payout was reserved but Paystack could not initiate the transfer. Review or reject the payout to release the funds.', [
                    'withdrawal_id' => $withdrawal->id,
                ], 502);
            }
        }

        AuditLogger::record('withdrawal.created_by_admin', $withdrawal, [], ['method' => $data['method']]);

        return $this->created($withdrawal->fresh('agent'), $data['method'] === 'manual'
            ? 'Payout recorded. Complete the bank transfer, then mark it paid.'
            : 'Paystack transfer initiated. Verify its status before marking it paid.');
    }
}
