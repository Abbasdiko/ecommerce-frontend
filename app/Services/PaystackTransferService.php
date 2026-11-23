<?php

namespace App\Services;

use App\Models\Withdrawal;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackTransferService
{
    public function initiate(Withdrawal $withdrawal): array
    {
        if (PaymentConfig::simulated()) {
            throw new RuntimeException('Switch payment configuration to live mode before initiating Paystack payouts.');
        }
        if (! $withdrawal->bank_code) {
            throw new RuntimeException('Add the Paystack bank code to the vendor bank account before using automatic payout.');
        }

        $headers = $this->headers();
        $claimed = Withdrawal::whereKey($withdrawal->id)
            ->whereNull('transfer_reference')
            ->whereNotIn('status', ['completed', 'rejected'])
            ->update([
                'payout_method' => 'paystack',
                'transfer_reference' => $withdrawal->reference,
                'status' => 'processing',
                'note' => 'Paystack transfer initiation in progress.',
            ]);
        if (! $claimed) {
            throw new RuntimeException('A Paystack transfer has already been initiated for this payout.');
        }
        $withdrawal->refresh();

        $baseUrl = rtrim((string) config('payments.gateways.paystack.base_url'), '/');

        $recipient = Http::withHeaders($headers)->connectTimeout(5)->timeout(30)->post($baseUrl . '/transferrecipient', [
            'type' => 'nuban',
            'name' => $withdrawal->account_name,
            'account_number' => $withdrawal->getAttributes()['account_number'],
            'bank_code' => $withdrawal->bank_code,
            'currency' => $withdrawal->wallet->currency,
        ])->throw()->json();

        $recipientCode = $recipient['data']['recipient_code'] ?? null;
        if (! ($recipient['status'] ?? false) || ! $recipientCode) {
            throw new RuntimeException('Paystack did not return a transfer recipient.');
        }

        $transfer = Http::withHeaders($headers)->connectTimeout(5)->timeout(30)->post($baseUrl . '/transfer', [
            'source' => 'balance',
            'amount' => (int) round((float) $withdrawal->net_amount * 100),
            'recipient' => $recipientCode,
            'reason' => "Vendor payout {$withdrawal->reference}",
            'reference' => $withdrawal->reference,
        ])->throw()->json();

        $transferData = $transfer['data'] ?? [];
        if (! ($transfer['status'] ?? false) || empty($transferData['transfer_code'])) {
            throw new RuntimeException($transfer['message'] ?? 'Paystack did not accept the transfer.');
        }

        $withdrawal->update([
            'payout_method' => 'paystack',
            'transfer_reference' => $transferData['reference'] ?? $withdrawal->reference,
            'transfer_code' => $transferData['transfer_code'],
            'status' => ($transferData['status'] ?? null) === 'success' ? 'completed' : 'processing',
            'processed_at' => ($transferData['status'] ?? null) === 'success' ? now() : null,
            'note' => 'Paystack transfer ' . ($transferData['status'] ?? 'initiated'),
        ]);

        return $transferData;
    }

    public function verify(Withdrawal $withdrawal): array
    {
        if (! $withdrawal->transfer_reference) {
            throw new RuntimeException('This payout has no Paystack transfer reference.');
        }

        $baseUrl = rtrim((string) config('payments.gateways.paystack.base_url'), '/');
        $response = Http::withHeaders($this->headers())
            ->connectTimeout(5)
            ->timeout(30)
            ->get($baseUrl . '/transfer/verify/' . urlencode($withdrawal->transfer_reference));
        if ($response->status() === 404) {
            $withdrawal->update([
                'status' => 'rejected',
                'processed_at' => now(),
                'note' => 'Paystack confirmed there is no transfer with this reference.',
            ]);

            return ['status' => 'failed', 'reason' => 'Paystack could not find a transfer with this reference.'];
        }
        $result = $response->throw()->json();

        $data = $result['data'] ?? [];
        if (($data['status'] ?? null) === 'success') {
            $withdrawal->update([
                'status' => 'completed',
                'processed_at' => now(),
                'note' => 'Paystack transfer verified as successful.',
            ]);
        } elseif (in_array($data['status'] ?? null, ['failed', 'reversed'], true)) {
            $withdrawal->update([
                'status' => 'rejected',
                'processed_at' => now(),
                'note' => 'Paystack transfer ' . $data['status'] . '.',
            ]);
        }

        return $data;
    }

    public function finalize(Withdrawal $withdrawal, string $otp): array
    {
        if (! $withdrawal->transfer_code) {
            throw new RuntimeException('This payout has no Paystack transfer code.');
        }

        $baseUrl = rtrim((string) config('payments.gateways.paystack.base_url'), '/');
        $result = Http::withHeaders($this->headers())
            ->connectTimeout(5)
            ->timeout(30)
            ->post($baseUrl . '/transfer/finalize_transfer', [
                'transfer_code' => $withdrawal->transfer_code,
                'otp' => $otp,
            ])
            ->throw()
            ->json();

        $data = $result['data'] ?? [];
        if (($data['status'] ?? null) === 'success') {
            $withdrawal->update([
                'status' => 'completed',
                'processed_at' => now(),
                'note' => 'Paystack transfer finalized successfully.',
            ]);
        } else {
            $withdrawal->update([
                'status' => 'processing',
                'note' => 'Paystack transfer finalization status: ' . ($data['status'] ?? 'unknown'),
            ]);
        }

        return $data;
    }

    private function headers(): array
    {
        $secret = PaymentConfig::credential('paystack', 'secret_key');
        if (! $secret) {
            throw new RuntimeException('Paystack secret key is not configured.');
        }

        return ['Authorization' => 'Bearer ' . $secret, 'Accept' => 'application/json'];
    }
}
