<?php

namespace App\Payments\Gateways;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;

class PaystackGateway extends AbstractGateway
{
    public function name(): string
    {
        return 'paystack';
    }

    protected function liveInitialize(Payment $payment): array
    {
        $res = Http::withToken($this->config('secret_key'))
            ->post($this->config('base_url').'/transaction/initialize', [
                'email' => $payment->user->email,
                'amount' => (int) round($payment->amount * 100), // kobo
                'reference' => $payment->reference,
                'currency' => $payment->currency,
                'callback_url' => ! empty($payment->meta['return_url'])
                    ? rtrim($payment->meta['return_url'], '/').'/'.$payment->reference
                    : null,
            ])->throw()->json();

        return [
            'reference' => $res['data']['reference'] ?? $payment->reference,
            'checkout_url' => $res['data']['authorization_url'] ?? null,
            'instructions' => null,
            'raw' => $res,
        ];
    }

    protected function liveVerify(Payment $payment): array
    {
        $ref = $payment->gateway_reference ?: $payment->reference;
        $res = Http::withToken($this->config('secret_key'))
            ->get($this->config('base_url').'/transaction/verify/'.$ref)
            ->throw()->json();

        $data = $res['data'] ?? [];

        return [
            'status' => match ($data['status'] ?? null) {
                'success' => 'successful',
                'failed', 'abandoned', 'reversed' => 'failed',
                default => 'pending',
            },
            'amount' => isset($data['amount']) ? $data['amount'] / 100 : 0,
            'currency' => $data['currency'] ?? $payment->currency,
            'raw' => $res,
        ];
    }

    protected function liveVerifyWebhookSignature(string $payload, ?string $signature): bool
    {
        return $signature && hash_equals(
            hash_hmac('sha512', $payload, (string) $this->config('secret_key')),
            $signature,
        );
    }
}
