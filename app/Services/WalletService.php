<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Ledger-safe wallet mutations (spec §61, §84). Every balance change writes an
 * immutable wallet_transactions row; balances are never blindly incremented.
 */
class WalletService
{
    public function walletFor(User $user, string $ownerType = 'customer'): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id, 'owner_type' => $ownerType],
            ['currency' => 'NGN'],
        );
    }

    /** Credit the AVAILABLE bucket (e.g. refund credit, matured referral commission). */
    public function creditAvailable(Wallet $wallet, float $amount, string $type, string $description, ?Model $reference = null): WalletTransaction
    {
        return $this->apply($wallet, $amount, 'credit', 'available', $type, $description, $reference);
    }

    /** Credit the PENDING bucket (referral commission awaiting maturity). */
    public function creditPending(Wallet $wallet, float $amount, string $type, string $description, ?Model $reference = null): WalletTransaction
    {
        return $this->apply($wallet, $amount, 'credit', 'pending', $type, $description, $reference);
    }

    /** Move funds from pending to available. */
    public function maturePending(Wallet $wallet, float $amount, string $description, ?Model $reference = null): void
    {
        DB::transaction(function () use ($wallet, $amount, $description, $reference) {
            $this->apply($wallet, $amount, 'debit', 'pending', 'maturation', "Matured: {$description}", $reference);
            $this->apply($wallet, $amount, 'credit', 'available', 'maturation', $description, $reference);
        });
    }

    /** Debit AVAILABLE for a settlement/payout. Throws if insufficient. */
    public function debitForSettlement(Wallet $wallet, float $amount, string $description, ?Model $reference = null): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $description, $reference) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->first();

            if ((float) $locked->available_balance < $amount) {
                throw new \RuntimeException('Insufficient available balance.');
            }

            $tx = $this->applyLocked($locked, $amount, 'debit', 'available', 'settlement', $description, $reference);
            $locked->increment('withdrawn_balance', $amount);

            return $tx;
        });
    }

    /** Debit AVAILABLE for a purchase without counting the spend as a withdrawal. */
    public function debitForPurchase(Wallet $wallet, float $amount, string $description, ?Model $reference = null): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $description, $reference) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->first();

            if ((float) $locked->available_balance < $amount) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'payment_method' => ['Insufficient wallet balance. Fund your wallet or choose another payment method.'],
                ]);
            }

            return $this->applyLocked($locked, $amount, 'debit', 'available', 'order_payment', $description, $reference);
        });
    }

    private function apply(Wallet $wallet, float $amount, string $direction, string $bucket, string $type, string $description, ?Model $reference): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $direction, $bucket, $type, $description, $reference) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->first();

            return $this->applyLocked($locked, $amount, $direction, $bucket, $type, $description, $reference);
        });
    }

    private function applyLocked(Wallet $wallet, float $amount, string $direction, string $bucket, string $type, string $description, ?Model $reference): WalletTransaction
    {
        $column = $bucket.'_balance';
        $before = (float) $wallet->{$column};
        $after = $direction === 'credit' ? $before + $amount : $before - $amount;

        $wallet->{$column} = $after;
        $wallet->save();

        $transaction = WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => $type,
            'reference_type' => $reference ? class_basename($reference) : null,
            'reference_id' => $reference?->getKey(),
            'amount' => $amount,
            'direction' => $direction,
            'balance_before' => $before,
            'balance_after' => $after,
            'bucket' => $bucket,
            'description' => $description,
            'status' => 'completed',
        ]);

        $wallet->refresh();

        return $transaction;
    }
}
