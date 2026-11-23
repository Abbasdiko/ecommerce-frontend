<?php

namespace App\Payments\Gateways;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;

/**
 * Remita RRR flow: initialise generates a Remita Retrieval Reference and sends the
 * payer to the hosted payment page; verify polls the transaction status endpoint.
 * Demo host: https://remitademo.net — live host: https://login.remita.net.
 */
class RemitaGateway extends AbstractGateway
{
    public function name(): string
    {
        return 'remita';
    }

    protected function baseUrl(): string
    {
        return rtrim($this->config('base_url', 'https://login.remita.net') ?: 'https://login.remita.net', '/');
    }

    protected function liveInitialize(Payment $payment): array
    {
        $merchantId = (string) $this->config('merchant_id');
        $serviceTypeId = (string) $this->config('service_type_id');
        $apiKey = (string) $this->config('api_key');
        $orderId = $payment->reference;
        $amount = number_format((float) $payment->amount, 2, '.', '');

        $apiHash = hash('sha512', $merchantId.$serviceTypeId.$orderId.$amount.$apiKey);

        $res = Http::withHeaders([
            'Authorization' => "remitaConsumerKey={$merchantId},remitaConsumerToken={$apiHash}",
            'Content-Type' => 'application/json',
        ])->post(
            $this->baseUrl().'/remita/exapp/api/v1/send/api/echannelsvc/merchant/api/paymentinit',
            [
                'serviceTypeId' => $serviceTypeId,
                'amount' => $amount,
                'orderId' => $orderId,
                'payerName' => trim(($payment->user->first_name ?? '').' '.($payment->user->last_name ?? '')) ?: $payment->user->email,
                'payerEmail' => $payment->user->email,
                'payerPhone' => $payment->user->phone ?? '',
                'description' => config('app.name').' order '.$orderId,
                'returnUrl' => rtrim((string) ($payment->meta['return_url'] ?? url('/payments/return')), '/').'/'.$payment->reference,
            ]
        )->throw();

        $body = $this->decodeJsonp($res->body());
        $rrr = $body['RRR'] ?? null;

        $checkoutUrl = null;
        if ($rrr) {
            $payHash = hash('sha512', $rrr.$apiKey.$merchantId);
            $checkoutUrl = $this->baseUrl()."/remita/ecomm/{$merchantId}/{$rrr}/{$payHash}/payment.reg";
        }

        return [
            'reference' => $rrr ?: $payment->reference,
            'checkout_url' => $checkoutUrl,
            'instructions' => $rrr ? ['rrr' => $rrr, 'note' => 'Pay with this Remita Retrieval Reference (RRR) at any bank or on the Remita portal.'] : null,
            'raw' => $body,
        ];
    }

    protected function liveVerify(Payment $payment): array
    {
        $merchantId = (string) $this->config('merchant_id');
        $apiKey = (string) $this->config('api_key');
        $rrr = $payment->gateway_reference ?: $payment->reference;

        $statusHash = hash('sha512', $rrr.$apiKey.$merchantId);

        $res = Http::withHeaders([
            'Authorization' => "remitaConsumerKey={$merchantId},remitaConsumerToken={$statusHash}",
        ])->get($this->baseUrl()."/remita/exapp/api/v1/send/api/echannelsvc/{$merchantId}/{$rrr}/{$statusHash}/status.reg")
            ->throw();

        $body = $this->decodeJsonp($res->body());
        $code = $body['status'] ?? ($body['statuscode'] ?? null);

        return [
            'status' => in_array((string) $code, ['00', '01'], true) ? 'successful' : 'pending',
            'amount' => (float) ($body['amount'] ?? 0),
            'currency' => $payment->currency,
            'raw' => $body,
        ];
    }

    protected function liveVerifyWebhookSignature(string $payload, ?string $signature): bool
    {
        // Remita notifications carry no HMAC; verifyAndSettle rechecks the RRR with Remita.
        return $signature === null;
    }

    /** Remita frequently wraps responses as `jsonp ({...})`. */
    private function decodeJsonp(string $raw): array
    {
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            return json_decode($m[0], true) ?: [];
        }

        return json_decode($raw, true) ?: [];
    }
}
