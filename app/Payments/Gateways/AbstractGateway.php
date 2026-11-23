<?php

namespace App\Payments\Gateways;

use App\Models\Payment;
use App\Payments\PaymentGateway;
use App\Services\PaymentConfig;
use Illuminate\Support\Str;

abstract class AbstractGateway implements PaymentGateway
{
    abstract public function name(): string;

    protected function simulated(): bool
    {
        return PaymentConfig::simulated();
    }

    protected function config(string $key, $default = null)
    {
        return PaymentConfig::credential($this->name(), $key, $default);
    }

    public function initialize(Payment $payment): array
    {
        if ($this->simulated()) {
            return $this->simulatedInitialize($payment);
        }

        return $this->liveInitialize($payment);
    }

    public function verify(Payment $payment): array
    {
        if ($this->simulated()) {
            return $this->simulatedVerify($payment);
        }

        return $this->liveVerify($payment);
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        return $this->simulated() ? false : $this->liveVerifyWebhookSignature($payload, $signature);
    }

    /* ---------------- simulated behaviour ---------------- */

    protected function simulatedInitialize(Payment $payment): array
    {
        $reference = strtoupper($this->name()).'_SIM_'.Str::random(16);
        $base = config('payments.simulated_checkout_url');
        $ticket = app(\App\Services\PortalLoginTicketService::class)->issue($payment->user);

        return [
            'reference' => $reference,
            'checkout_url' => rtrim($base, '/').'/'.$payment->reference.'?ticket='.urlencode($ticket),
            'instructions' => null,
            'raw' => ['simulated' => true, 'gateway' => $this->name()],
        ];
    }

    protected function simulatedVerify(Payment $payment): array
    {
        return [
            'status' => 'successful',
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'raw' => ['simulated' => true, 'verified_at' => now()->toIso8601String()],
        ];
    }

    /* ---------------- live behaviour (override per gateway) ---------------- */

    protected function liveInitialize(Payment $payment): array
    {
        throw new \RuntimeException(ucfirst($this->name()).' live mode is not configured. Add API keys and implement liveInitialize().');
    }

    protected function liveVerify(Payment $payment): array
    {
        throw new \RuntimeException(ucfirst($this->name()).' live mode is not configured.');
    }

    protected function liveVerifyWebhookSignature(string $payload, ?string $signature): bool
    {
        return false;
    }
}
