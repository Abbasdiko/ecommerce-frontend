<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Settlement;
use App\Services\AuditLogger;
use App\Services\WalletService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    use ApiResponse;

    private const MIN_PAYOUT = 1000;
    private const FEE = 50;

    public function __construct(private WalletService $wallets)
    {
    }

    public function index(Request $request)
    {
        $settlements = Settlement::where('user_id', $request->user()->id)
            ->with('bankAccount')
            ->latest()
            ->paginate($this->perPage($request));

        return $this->ok($settlements);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $wallet = $this->wallets->walletFor($user);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:' . self::MIN_PAYOUT],
            'bank_account_id' => ['required', 'exists:bank_accounts,id'],
        ]);

        $bank = $user->bankAccounts()->whereKey($data['bank_account_id'])->first();
        abort_unless($bank, 422, 'Select one of your saved bank accounts.');

        if ($data['amount'] > (float) $wallet->available_balance) {
            return $this->fail('Requested amount exceeds your available balance.', null, 422);
        }

        if ($user->settlements()->where('status', 'pending')->exists()) {
            return $this->fail('You already have a payout request awaiting review.', null, 409);
        }

        $settlement = Settlement::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'bank_account_id' => $bank->id,
            'amount' => $data['amount'],
            'fee' => self::FEE,
            'net_amount' => $data['amount'] - self::FEE,
            'status' => 'pending',
        ]);
        AuditLogger::record('settlement.requested', $settlement);

        return $this->created($settlement->load('bankAccount'), 'Payout request submitted');
    }
}
