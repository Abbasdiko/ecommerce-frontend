<?php

namespace App\Payments;

use App\Models\Payment;

interface PaymentGateway
{
    /**
     * Begin a payment. Returns:
     *  [
     *    'reference'    => gateway transaction reference,
     *    'checkout_url' => URL the customer is sent to (null for offline methods),
     *    'instructions' => optional array shown to the user (bank transfer details),
     *    'raw'          => provider payload,
     *  ]
     */
    public function initialize(Payment $payment): array;

    /**
     * Verify a transaction server-side (spec §47). Returns:
     *  ['status' => successful|failed|pending, 'amount' => float, 'currency' => string, 'raw' => array]
     */
    public function verify(Payment $payment): array;

    /**
     * Validate an inbound webhook signature (spec §140). Return true if trusted.
     */
    public function verifyWebhookSignature(string $payload, ?string $signature): bool;
}
