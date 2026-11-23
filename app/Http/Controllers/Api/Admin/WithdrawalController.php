<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\AuditLogger;
use App\Services\PaystackTransferService;
use App\Services\WalletService;
use App\Support\ApiResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WithdrawalController extends Controller
{
    use ApiResponse;

    public function __construct(private WalletService $wallets, private PaystackTransferService $paystack)
    {
    }

    public function index(Request $request)
    {
        $this->authorizePermission($request, 'withdrawal.view');

        $withdrawals = Withdrawal::query()
            ->with(['agent:id,business_name', 'bankAccount'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($this->perPage($request));

        return $this->ok($withdrawals);
    }

    public function transition(Request $request, Withdrawal $withdrawal)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'processing', 'completed', 'rejected'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $permission = $data['status'] === 'rejected' ? 'withdrawal.reject' : 'withdrawal.approve';
        $this->authorizePermission($request, $permission);

        $outcome = DB::transaction(function () use ($withdrawal, $data, $request) {
            $locked = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, ['completed', 'rejected'], true)) {
                return 'final';
            }
            if ($locked->payout_method === 'paystack' && $locked->transfer_reference
                && in_array($data['status'], ['completed', 'rejected'], true)) {
                return 'verify';
            }

            if ($data['status'] === 'rejected') {
                // Return the held funds to available (spec §84 — reversal, not deletion).
                $wallet = Wallet::find($locked->wallet_id);
                if ($wallet) {
                    $this->wallets->creditAvailable(
                        $wallet,
                        (float) $locked->amount,
                        'adjustment',
                        "Withdrawal {$locked->reference} rejected — funds returned",
                        $locked,
                    );
                    $wallet->decrement('withdrawn_balance', min((float) $wallet->withdrawn_balance, (float) $locked->amount));
                }
            }

            $locked->update([
                'status' => $data['status'],
                'note' => $data['note'] ?? $locked->note,
                'processed_by' => $request->user()->id,
                'processed_at' => in_array($data['status'], ['completed', 'rejected'], true) ? now() : $locked->processed_at,
                'payout_method' => $data['status'] === 'completed' ? 'manual' : $locked->payout_method,
            ]);

            return 'updated';
        });
        if ($outcome === 'final') {
            return $this->fail('This withdrawal is already finalised.', null, 409);
        }
        if ($outcome === 'verify') {
            return $this->fail('Verify the Paystack transfer before completing or releasing these funds.', null, 409);
        }

        AuditLogger::record('withdrawal.' . $data['status'], $withdrawal);

        return $this->ok($withdrawal->fresh('agent'), 'Withdrawal ' . $data['status']);
    }

    public function paystack(Request $request, Withdrawal $withdrawal)
    {
        $this->authorizePermission($request, 'withdrawal.approve');
        if (in_array($withdrawal->status, ['completed', 'rejected'], true)) {
            return $this->fail('This withdrawal is already finalised.', null, 409);
        }
        try {
            $this->paystack->initiate($withdrawal);
        } catch (RequestException|ConnectionException|\RuntimeException $e) {
            return $this->fail($e->getMessage(), null, 502);
        }
        $withdrawal->update(['processed_by' => $request->user()->id]);
        AuditLogger::record('withdrawal.paystack_initiated', $withdrawal);

        return $this->ok($withdrawal->fresh('agent'), 'Paystack transfer initiated');
    }

    public function verifyPaystack(Request $request, Withdrawal $withdrawal)
    {
        $this->authorizePermission($request, 'withdrawal.approve');
        if ($withdrawal->payout_method !== 'paystack' || in_array($withdrawal->status, ['completed', 'rejected'], true)) {
            return $this->fail('This payout cannot be verified as an active Paystack transfer.', null, 409);
        }

        $result = DB::transaction(function () use ($withdrawal) {
            $locked = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            if ($locked->payout_method !== 'paystack' || in_array($locked->status, ['completed', 'rejected'], true)) {
                return null;
            }

            return $this->paystack->verify($locked);
        });
        if (! $result) {
            return $this->fail('This payout has already been finalised.', null, 409);
        }
        if (($result['status'] ?? null) === 'failed' || ($result['status'] ?? null) === 'reversed') {
            DB::transaction(function () use ($withdrawal) {
                $locked = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== 'rejected') {
                    return;
                }
                $wallet = Wallet::find($withdrawal->wallet_id);
                if ($wallet) {
                    $this->wallets->creditAvailable(
                        $wallet,
                        (float) $withdrawal->amount,
                        'adjustment',
                        "Paystack payout {$withdrawal->reference} failed — funds returned",
                        $withdrawal,
                    );
                    $wallet->decrement('withdrawn_balance', min((float) $wallet->withdrawn_balance, (float) $withdrawal->amount));
                }
            });
        }

        AuditLogger::record('withdrawal.paystack_verified', $withdrawal, [], ['status' => $result['status'] ?? 'unknown']);

        return $this->ok($withdrawal->fresh('agent'), 'Paystack transfer status: ' . ($result['status'] ?? 'unknown'));
    }

    public function finalizePaystack(Request $request, Withdrawal $withdrawal)
    {
        $this->authorizePermission($request, 'withdrawal.approve');
        $data = $request->validate(['otp' => ['required', 'string', 'max:20']]);
        if ($withdrawal->payout_method !== 'paystack'
            || ! $withdrawal->transfer_code
            || in_array($withdrawal->status, ['completed', 'rejected'], true)) {
            return $this->fail('This payout cannot be finalized through Paystack.', null, 409);
        }

        try {
            $result = $this->paystack->finalize($withdrawal, $data['otp']);
        } catch (RequestException|ConnectionException|\RuntimeException $e) {
            return $this->fail($e->getMessage(), null, 502);
        }

        AuditLogger::record('withdrawal.paystack_finalized', $withdrawal, [], ['status' => $result['status'] ?? 'unknown']);

        return $this->ok($withdrawal->fresh('agent'), 'Paystack transfer status: ' . ($result['status'] ?? 'unknown'));
    }
}
