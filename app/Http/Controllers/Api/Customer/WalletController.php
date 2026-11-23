<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\PaymentConfig;
use App\Services\PaymentService;
use App\Services\WalletService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    use ApiResponse;

    public function __construct(
        private WalletService $wallets,
        private PaymentService $payments,
    ) {}

    public function show(Request $request)
    {
        $wallet = $this->wallets->walletFor($request->user());

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
        ]);
    }

    public function deposit(Request $request)
    {
        $methods = array_values(array_intersect(
            PaymentConfig::checkoutMethods(),
            ['paystack', 'flutterwave', 'remita', 'bank_transfer'],
        ));
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:100', 'max:10000000'],
            'payment_method' => ['required', Rule::in($methods)],
        ]);

        $wallet = $this->wallets->walletFor($request->user());
        $payment = $this->payments->createWalletTopUp(
            $request->user(),
            $wallet,
            $data['payment_method'],
            (float) $data['amount'],
        );
        $payment->update(['meta' => array_merge($payment->meta ?? [], ['return_url' => url('/payments/return')])]);

        $result = $this->payments->initialize($payment);
        AuditLogger::record('wallet.top_up_requested', $payment);

        return $this->created([
            'payment' => [
                'reference' => $payment->reference,
                'status' => $payment->fresh()->status,
                'gateway' => $payment->gateway,
                'amount' => (float) $payment->amount,
            ],
            'checkout_url' => $result['checkout_url'] ?? null,
            'instructions' => $result['instructions'] ?? null,
        ], 'Wallet funding initiated');
    }
}
