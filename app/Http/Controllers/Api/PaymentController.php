<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Payments\PaymentManager;
use App\Services\AuditLogger;
use App\Services\PaymentConfig;
use App\Services\PaymentService;
use App\Services\WalletService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private PaymentService $payments,
        private PaymentManager $manager,
        private WalletService $wallets,
    ) {}

    public function options(Request $request)
    {
        return $this->ok([
            'gateways' => $this->manager->options(),
            'simulated' => PaymentConfig::simulated(),
            'bank_accounts' => PaymentConfig::bankAccounts(),
            'wallet_balance' => $request->user()->hasRole('customer')
                ? (float) $this->wallets->walletFor($request->user())->available_balance
                : null,
        ]);
    }

    public function index(Request $request)
    {
        $payments = Payment::where('user_id', $request->user()->id)
            ->latest()
            ->paginate($this->perPage($request));

        return PaymentResource::collection($payments);
    }

    public function show(Request $request, Payment $payment)
    {
        abort_unless($payment->user_id === $request->user()->id, 403);

        return $this->ok(new PaymentResource($payment));
    }

    /** Client calls this after returning from the gateway (spec §44). */
    public function verify(Request $request, string $reference)
    {
        $payment = Payment::where('reference', $reference)->firstOrFail();
        abort_unless($payment->user_id === $request->user()->id, 403);

        $payment = $this->payments->verifyAndSettle($payment);
        AuditLogger::record('payment.verify_attempt', $payment, [], ['status' => $payment->status]);

        return $this->ok(new PaymentResource($payment), match ($payment->status) {
            'successful' => 'Payment verified',
            'awaiting_verification' => 'Awaiting bank transfer confirmation',
            default => 'Payment not completed',
        });
    }

    /** Bank transfer proof upload (spec §49, §81). */
    public function uploadProof(Request $request, string $reference)
    {
        $payment = Payment::where('reference', $reference)->firstOrFail();
        abort_unless($payment->user_id === $request->user()->id, 403);
        abort_unless($payment->gateway === 'bank_transfer', 422, 'Proof is only for bank transfers.');

        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $request->file('proof')->store('payment-proofs', 'public');
        $payment->update(['proof_path' => $path, 'status' => 'awaiting_verification']);
        AuditLogger::record('payment.proof_uploaded', $payment);

        return $this->ok(new PaymentResource($payment->fresh()), 'Proof uploaded — an administrator will confirm your transfer.');
    }

    /** Gateway webhook (spec §113, §140). Public, signature-checked, idempotent. */
    public function webhook(Request $request, string $gateway)
    {
        abort_unless(in_array($gateway, PaymentManager::GATEWAYS, true), 404);

        $raw = $request->getContent();
        $signature = $request->header('x-paystack-signature')
            ?? $request->header('verif-hash')
            ?? $request->header('x-webhook-signature');

        if (! $this->manager->gateway($gateway)->verifyWebhookSignature($raw, $signature)) {
            return $this->fail('Invalid signature.', null, 401);
        }

        $payload = $request->json()->all();
        $hook = $this->payments->recordWebhook($gateway, $payload, $signature, $raw);

        $reference = $payload['data']['reference'] ?? ($payload['reference'] ?? null);
        if ($reference) {
            $payment = Payment::where('reference', $reference)
                ->orWhere('gateway_reference', $reference)
                ->first();
            if ($payment) {
                $this->payments->verifyAndSettle($payment);
            }
        }

        $hook->update(['processed' => true, 'processed_at' => now()]);

        return $this->ok(null, 'ok');
    }
}
