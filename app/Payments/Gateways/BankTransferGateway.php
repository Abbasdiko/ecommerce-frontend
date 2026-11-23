<?php

namespace App\Payments\Gateways;

use App\Models\Payment;
use App\Payments\PaymentGateway;
use App\Services\PaymentConfig;

/**
 * Offline method: the platform shows its bank details, the payer transfers funds
 * and (optionally) uploads proof. An admin confirms it manually (spec §49).
 */
class BankTransferGateway implements PaymentGateway
{
    public function initialize(Payment $payment): array
    {
        $payment->update(['status' => 'awaiting_verification']);

        return [
            'reference' => $payment->reference,
            'checkout_url' => null,
            'instructions' => [
                'accounts' => PaymentConfig::bankAccounts(),
                'amount' => (float) $payment->amount,
                'narration' => $payment->reference,
                'note' => $payment->purpose === 'wallet_topup'
                    ? 'Use the reference above as the transfer narration, then upload your proof of payment. An administrator will confirm the transfer and credit your wallet.'
                    : 'Use the reference above as the transfer narration, then upload your proof of payment. An administrator will confirm it.',
            ],
            'raw' => ['method' => 'bank_transfer'],
        ];
    }

    public function verify(Payment $payment): array
    {
        // Bank transfers are only ever confirmed by an admin action, never auto-verified.
        return [
            'status' => $payment->status === 'successful' ? 'successful' : 'pending',
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'raw' => [],
        ];
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        return false;
    }
}
