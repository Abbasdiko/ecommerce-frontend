<?php

namespace App\Payments\Gateways;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;

class FlutterwaveGateway extends AbstractGateway
{
    public function name(): string
    {
        return 'flutterwave';
    }

    protected function baseUrl(): string
    {
        return rtrim($this->config('base_url', 'https://api.flutterwave.com/v3'), '/');
    }

    protected function liveInitialize(Payment $payment): array
    {
        $return = $payment->meta['return_url'] ?? config('payments.simulated_checkout_url');

        $res = Http::withToken($this->config('secret_key'))
            ->acceptJson()
            ->post($this->baseUrl() . '/payments', [
                'tx_ref' => $payment->reference,
                'amount' => round((float) $payment->amount, 2),
                'currency' => $payment->currency,
                'redirect_url' => rtrim($return, '/') . '/' . $payment->reference,
                'customer' => [
                    'email' => $payment->user->email,
                    'name' => trim(($payment->user->first_name ?? '') . ' ' . ($payment->user->last_name ?? '')) ?: $payment->user->email,
                ],
                'customizations' => [
                    'title' => config('app.name') . ' payment',
                ],
            ])->throw()->json();

        return [
            'reference' => $payment->reference,
            'checkout_url' => $res['data']['link'] ?? null,
            'instructions' => null,
            'raw' => $res,
        ];
    }

    protected function liveVerify(Payment $payment): array
    {
        $ref = $payment->gateway_reference ?: $payment->reference;
        $res = Http::withToken($this->config('secret_key'))
            ->acceptJson()
            ->get($this->baseUrl() . '/transactions/verify_by_reference', ['tx_ref' => $ref])
            ->throw()->json();

        $data = $res['data'] ?? [];

        return [
            'status' => ($data['status'] ?? null) === 'successful' ? 'successful' : ($data['status'] ?? 'failed'),
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => $data['currency'] ?? $payment->currency,
            'raw' => $res,
        ];
    }

    protected function liveVerifyWebhookSignature(string $payload, ?string $signature): bool
    {
        // Flutterwave sends the configured "Secret hash" verbatim in the verif-hash header.
        $hash = (string) $this->config('encryption_key');

        return $hash !== '' && $signature !== null && hash_equals($hash, $signature);
    }
}
