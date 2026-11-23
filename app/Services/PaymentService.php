<?php

namespace App\Services;

use App\Exceptions\PaymentGatewayException;
use App\Models\AgentInvoice;
use App\Models\AgentSubscription;
use App\Models\Payment;
use App\Models\PaymentWebhook;
use App\Models\User;
use App\Models\Wallet;
use App\Payments\PaymentManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private PaymentManager $manager,
        private SubscriptionService $subscriptions,
    ) {}

    public function createForSubscription(AgentSubscription $subscription, User $user, string $gateway): Payment
    {
        return $this->create($user, $gateway, 'subscription', $subscription, (float) $subscription->amount);
    }

    public function createForAgentInvoice(AgentInvoice $invoice, User $user, string $gateway): Payment
    {
        if ((int) $invoice->agent->user_id !== (int) $user->id || (float) $invoice->total <= 0) {
            throw new \InvalidArgumentException('This invoice cannot be paid by the selected user.');
        }

        return $this->create($user, $gateway, 'agent_invoice', $invoice, (float) $invoice->total);
    }

    public function createWalletTopUp(User $user, Wallet $wallet, string $gateway, float $amount): Payment
    {
        $payment = $this->create($user, $gateway, 'wallet_topup', $wallet, $amount);
        $payment->update(['currency' => $wallet->currency]);

        return $payment->fresh();
    }

    public function chargeWalletForOrder(User $user, Wallet $wallet, \App\Models\Order $order): Payment
    {
        $payment = $this->create($user, 'wallet', 'order', $order, (float) $order->total);
        $payment->update([
            'currency' => $order->currency,
            'status' => 'successful',
            'paid_at' => now(),
            'verified_at' => now(),
        ]);

        app(WalletService::class)->debitForPurchase(
            $wallet,
            (float) $order->total,
            "Payment for order {$order->order_number}",
            $order,
        );
        $this->settle($payment->fresh());

        return $payment->fresh();
    }

    public function create(User $user, string $gateway, string $purpose, Model $payable, float $amount): Payment
    {
        return Payment::create([
            'gateway' => $gateway,
            'method' => $gateway,
            'purpose' => $purpose,
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => config('payments.default_currency'),
            'status' => 'pending',
        ]);
    }

    public function initialize(Payment $payment): array
    {
        if (in_array($payment->gateway, PaymentConfig::GATEWAYS, true)
            && ! PaymentConfig::simulated()
            && ! PaymentConfig::gatewayReady($payment->gateway)) {
            throw ValidationException::withMessages([
                'payment_method' => [ucfirst($payment->gateway).' is not configured for live payments. Ask an administrator to configure it or choose another payment method.'],
            ]);
        }

        try {
            $result = $this->manager->gateway($payment->gateway)->initialize($payment);
        } catch (ConnectionException|RequestException $exception) {
            report($exception);
            $payment->update(['status' => 'failed']);
            throw new PaymentGatewayException('The payment provider could not be reached. No payment was completed; please try again or choose another method.', $exception);
        }
        $checkoutUrl = $result['checkout_url'] ?? null;
        if (in_array($payment->gateway, PaymentConfig::GATEWAYS, true)) {
            $parts = is_string($checkoutUrl) ? parse_url($checkoutUrl) : false;
            $validScheme = PaymentConfig::simulated()
                ? in_array($parts['scheme'] ?? '', ['http', 'https'], true)
                : ($parts['scheme'] ?? '') === 'https';
            if ($parts === false || empty($parts['host']) || ! $validScheme) {
                $payment->update(['status' => 'failed']);
                throw ValidationException::withMessages([
                    'payment_method' => [ucfirst($payment->gateway).' did not return a valid hosted checkout link. No payment was completed. Please choose another method or contact support.'],
                ]);
            }
        }

        $payment->forceFill([
            'gateway_reference' => $result['reference'] ?? $payment->gateway_reference,
            'status' => $payment->gateway === 'bank_transfer' ? 'awaiting_verification' : 'processing',
            'meta' => array_merge($payment->meta ?? [], [
                'init' => $result['raw'] ?? [],
                'instructions' => $result['instructions'] ?? null,
            ]),
        ])->save();

        if ($payment->purpose === 'order') {
            \App\Models\Order::whereKey($payment->payable_id)
                ->update(['payment_status' => $payment->gateway === 'bank_transfer' ? 'awaiting_verification' : 'processing']);
        }

        return $result;
    }

    /**
     * Verify server-side and settle. Idempotent (spec §48) — a payment already
     * marked successful is returned untouched.
     */
    public function verifyAndSettle(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($locked->status === 'successful') {
                return $locked;
            }

            $result = $this->manager->gateway($locked->gateway)->verify($locked);

            if (($result['status'] ?? null) !== 'successful') {
                if (in_array($result['status'] ?? null, ['failed', 'cancelled'], true)) {
                    $locked->update([
                        'status' => $result['status'],
                        'meta' => array_merge($locked->meta ?? [], ['verify' => $result['raw'] ?? []]),
                    ]);
                    if ($locked->purpose === 'order') {
                        \App\Models\Order::whereKey($locked->payable_id)
                            ->update(['payment_status' => $result['status']]);
                    }
                    if ($locked->purpose === 'agent_invoice') {
                        AgentInvoice::whereKey($locked->payable_id)->where('status', 'payment_pending')
                            ->update(['status' => 'payment_failed']);
                    }
                }

                return $locked;
            }

            // Amount / currency guard (spec §47).
            if (round((float) $result['amount'], 2) < round((float) $locked->amount, 2)
                || strtoupper($result['currency'] ?? '') !== strtoupper($locked->currency)) {
                $locked->update(['status' => 'failed', 'meta' => array_merge($locked->meta ?? [], ['verify' => $result['raw'] ?? [], 'mismatch' => true])]);
                if ($locked->purpose === 'agent_invoice') {
                    AgentInvoice::whereKey($locked->payable_id)->where('status', 'payment_pending')
                        ->update(['status' => 'payment_failed']);
                }
                if ($locked->purpose === 'order') {
                    \App\Models\Order::whereKey($locked->payable_id)
                        ->update(['payment_status' => 'failed']);
                }

                return $locked;
            }

            $locked->update([
                'status' => 'successful',
                'paid_at' => now(),
                'verified_at' => now(),
                'meta' => array_merge($locked->meta ?? [], ['verify' => $result['raw'] ?? []]),
            ]);

            $this->settle($locked);

            return $locked;
        });
    }

    /** Manual confirmation for bank transfers (spec §49). */
    public function confirmBankTransfer(Payment $payment, User $admin): Payment
    {
        return DB::transaction(function () use ($payment, $admin) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($locked->status === 'successful') {
                return $locked;
            }

            $locked->update([
                'status' => 'successful',
                'paid_at' => now(),
                'verified_at' => now(),
                'verified_by' => $admin->id,
            ]);

            $this->settle($locked);

            return $locked;
        });
    }

    private function settle(Payment $payment): void
    {
        if ($payment->purpose === 'wallet_topup') {
            $wallet = $payment->payable;

            if (! ($wallet instanceof Wallet)
                || (int) $wallet->user_id !== (int) $payment->user_id
                || $wallet->owner_type !== 'customer') {
                throw new \RuntimeException('Wallet funding payment has an invalid customer wallet.');
            }

            app(WalletService::class)->creditAvailable(
                $wallet,
                (float) $payment->amount,
                'wallet_topup',
                "Wallet funded via {$payment->gateway}",
                $payment,
            );
        }

        if ($payment->purpose === 'subscription') {
            $subscription = AgentSubscription::find($payment->payable_id);
            if ($subscription && $subscription->status !== 'active') {
                $subscription->update(['payment_id' => $payment->id]);
                $this->subscriptions->activate($subscription, $payment->reference);
            }
        }

        if ($payment->purpose === 'agent_invoice') {
            $invoice = AgentInvoice::with('items.subscription')->find($payment->payable_id);
            if (! $invoice
                || (int) $invoice->agent->user_id !== (int) $payment->user_id
                || round((float) $invoice->total, 2) !== round((float) $payment->amount, 2)) {
                throw new \RuntimeException('Agent invoice payment does not match its invoice.');
            }

            if ($invoice->status !== 'paid') {
                $invoice->update(['status' => 'paid', 'paid_at' => $payment->paid_at ?? now()]);

                foreach ($invoice->items as $item) {
                    $subscription = $item->subscription;
                    if ($subscription && $subscription->status !== 'active') {
                        $subscription->update(['payment_id' => $payment->id]);
                        $this->subscriptions->activate($subscription, $payment->reference);
                    }
                }
            }
        }

        if ($payment->purpose === 'order') {
            $order = \App\Models\Order::with('items')->find($payment->payable_id);
            if ($order && $order->payment_status !== 'successful') {
                $order->update([
                    'payment_status' => 'successful',
                    'order_status' => $order->order_status === 'pending' ? 'confirmed' : $order->order_status,
                ]);
                \App\Models\OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status' => 'confirmed',
                    'note' => 'Payment received',
                ]);

                foreach ($order->items as $item) {
                    if ($item->listing_id) {
                        \App\Models\Listing::whereKey($item->listing_id)->increment('sales_count', $item->quantity);
                    }
                }

                $this->creditReferral($order);
                DB::afterCommit(function () use ($order) {
                    app(OrderNotificationService::class)->paymentConfirmed($order->fresh());
                });
            }
        }
    }

    /** Referral commission for the customer's referrer (spec §55 / §64), pending until delivery. */
    private function creditReferral(\App\Models\Order $order): void
    {
        $customer = \App\Models\User::find($order->customer_id);
        if (! $customer || ! $customer->referred_by) {
            return;
        }
        if (\App\Models\ReferralCommission::where('order_id', $order->id)->exists()) {
            return;
        }

        $rate = 2.0;
        $amount = round((float) $order->subtotal * $rate / 100, 2);

        $rc = \App\Models\ReferralCommission::create([
            'referrer_user_id' => $customer->referred_by,
            'referred_user_id' => $customer->id,
            'order_id' => $order->id,
            'commission_base' => $order->subtotal,
            'commission_rate' => $rate,
            'commission_amount' => $amount,
            'status' => 'pending',
        ]);

        $referrer = \App\Models\User::find($customer->referred_by);
        if ($referrer) {
            app(WalletService::class)->creditPending(
                app(WalletService::class)->walletFor($referrer, 'customer'),
                $amount,
                'referral_commission',
                "Pending referral commission — order {$order->order_number}",
                $rc,
            );
        }
    }

    public function recordWebhook(string $gateway, array $payload, ?string $signature, ?string $rawBody): PaymentWebhook
    {
        return PaymentWebhook::create([
            'gateway' => $gateway,
            'event' => $payload['event'] ?? ($payload['type'] ?? null),
            'reference' => $payload['data']['reference'] ?? ($payload['reference'] ?? null),
            'payload' => $payload,
            'signature' => $signature,
        ]);
    }
}
