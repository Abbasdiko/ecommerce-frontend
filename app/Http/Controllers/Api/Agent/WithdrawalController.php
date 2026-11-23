<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\AuditLogger;
use App\Services\WalletService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    use ApiResponse;

    public function __construct(private WalletService $wallets)
    {
    }

    private function agent(Request $request)
    {
        $agent = $request->user()->agent;
        abort_unless($agent, 403, 'Complete your business profile first.');

        return $agent;
    }

    public function index(Request $request)
    {
        $agent = $this->agent($request);

        return $this->ok(
            Withdrawal::where('agent_id', $agent->id)->latest()->paginate($this->perPage($request))
        );
    }

    public function store(Request $request)
    {
        $agent = $this->agent($request);
        $wallet = $this->wallets->walletFor($request->user(), 'agent');

        $min = (float) config('payments.withdrawals.min_amount');
        $fee = (float) config('payments.withdrawals.fee');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:' . $min],
            'bank_account_id' => ['required', 'exists:bank_accounts,id'],
        ]);

        $bank = $request->user()->bankAccounts()->whereKey($data['bank_account_id'])->first();
        abort_unless($bank, 422, 'Select one of your saved bank accounts.');

        if ($data['amount'] > (float) $wallet->available_balance) {
            return $this->fail('Requested amount exceeds your available balance.', null, 422);
        }
        if ($agent->withdrawals()->whereIn('status', ['pending', 'approved', 'processing'])->exists()) {
            return $this->fail('You already have a withdrawal in progress.', null, 409);
        }

        $withdrawal = Withdrawal::create([
            'agent_id' => $agent->id,
            'wallet_id' => $wallet->id,
            'bank_account_id' => $bank->id,
            'amount' => $data['amount'],
            'fee' => $fee,
            'net_amount' => $data['amount'] - $fee,
            'bank_name' => $bank->bank_name,
            'bank_code' => $bank->bank_code,
            'account_name' => $bank->account_name,
            'account_number' => $bank->getAttributes()['account_number'] ?? null,
            'status' => 'pending',
        ]);

        // Hold the funds immediately by debiting available (spec §62, §84).
        $this->wallets->debitForSettlement($wallet, (float) $data['amount'], "Withdrawal {$withdrawal->reference}", $withdrawal);
        AuditLogger::record('withdrawal.requested', $withdrawal);

        return $this->created($withdrawal->fresh(), 'Withdrawal request submitted');
    }
}
