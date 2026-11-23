<?php

namespace App\Services;

use App\Models\AgentOrder;
use App\Models\Commission;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a validated cart into a real multi-agent order (spec §38, §90, §93).
 * Prices, discounts and commission are all computed server-side.
 */
class OrderCreationService
{
    public function __construct(
        private CommissionResolver $commissions,
        private CouponValidator $coupons,
        private PaymentService $payments,
    ) {}

    /**
     * @param  array{items: array<int,array{listing_id:int,quantity:int}>, shipping_address_id:?int, billing_address_id:?int, coupon_code:?string, payment_method:string, gateway:?string, notes:?string}  $data
     */
    public function quote(User $customer, array $data): array
    {
        return $this->build($customer, $data, persist: false);
    }

    public function place(User $customer, array $data): array
    {
        return DB::transaction(fn () => $this->build($customer, $data, persist: true));
    }

    private function build(User $customer, array $data, bool $persist): array
    {
        $lines = $this->resolveLines($data['items'] ?? [], lock: $persist);

        if (empty($lines)) {
            throw ValidationException::withMessages(['items' => ['Your cart is empty or the items are no longer available.']]);
        }

        // ---- coupon ----
        $couponResult = ['valid' => false, 'coupon' => null, 'discount' => 0.0, 'eligible_subtotal' => 0.0, 'reason' => null];
        if (! empty($data['coupon_code'])) {
            $couponResult = $this->coupons->evaluate($data['coupon_code'], $customer, $lines);
            if ($persist && ! $couponResult['valid']) {
                throw ValidationException::withMessages(['coupon_code' => [$couponResult['reason']]]);
            }
        }
        /** @var Coupon|null $coupon */
        $coupon = $couponResult['coupon'];
        $totalDiscount = (float) $couponResult['discount'];
        $eligibleSubtotal = (float) $couponResult['eligible_subtotal'];

        $subtotal = (float) collect($lines)->sum('subtotal');

        // ---- delivery (one fee per order, waived over threshold) ----
        $deliveryFee = (float) (Setting::value('delivery.default_fee') ?? 1500);
        $freeOver = (float) (Setting::value('delivery.free_over') ?? 0);
        if ($freeOver > 0 && $subtotal >= $freeOver) {
            $deliveryFee = 0;
        }

        $taxPct = (float) (Setting::value('platform.tax_percentage') ?? 0);

        // ---- allocate discount + commission per line ----
        $byAgent = [];
        foreach ($lines as &$line) {
            /** @var Listing $listing */
            $listing = $line['listing'];

            $eligibleForCoupon = $coupon
                && ($coupon->owner_type === 'platform' || $listing->agent_id === (int) $coupon->owner_id);

            $line['discount'] = ($eligibleForCoupon && $eligibleSubtotal > 0)
                ? round($totalDiscount * ($line['subtotal'] / $eligibleSubtotal), 2)
                : 0.0;

            $commissionBase = $line['subtotal'] - $line['discount'];
            $rate = $this->commissions->rateFor(
                $listing->agent_id,
                $listing->service_id,
                $listing->category_id,
                optional($listing->subscription->package ?? null)->commission_rate,
            );
            $line['commission'] = $this->commissions->compute($commissionBase, $rate);
            $line['agent_earning'] = round($commissionBase - $line['commission'], 2);
            $line['tax'] = round($commissionBase * $taxPct / 100, 2);

            $byAgent[$listing->agent_id][] = $line;
        }
        unset($line);

        $tax = (float) collect($lines)->sum('tax');
        $total = round($subtotal - $totalDiscount + $deliveryFee + $tax, 2);

        $breakdown = [
            'subtotal' => round($subtotal, 2),
            'discount' => round($totalDiscount, 2),
            'delivery_fee' => $deliveryFee,
            'tax' => round($tax, 2),
            'total' => $total,
            'coupon' => $coupon ? ['code' => $coupon->code, 'valid' => true] : ($couponResult['reason'] ? ['code' => $data['coupon_code'] ?? null, 'valid' => false, 'reason' => $couponResult['reason']] : null),
            'items' => collect($lines)->map(fn ($l) => [
                'listing_id' => $l['listing']->id,
                'name' => $l['listing']->name,
                'unit_price' => $l['unit_price'],
                'quantity' => $l['quantity'],
                'selected_options' => $l['selected_options'],
                'discount' => $l['discount'],
                'subtotal' => $l['subtotal'],
            ])->values(),
        ];

        if (! $persist) {
            return ['quote' => $breakdown];
        }

        // ---- persist ----
        $order = Order::create([
            'customer_id' => $customer->id,
            'subtotal' => $subtotal,
            'discount' => $totalDiscount,
            'delivery_fee' => $deliveryFee,
            'tax' => $tax,
            'total' => $total,
            'currency' => Setting::value('platform.currency') ?? 'NGN',
            'payment_status' => $data['payment_method'] === 'bank_transfer' ? 'awaiting_verification' : 'pending',
            'order_status' => 'pending',
            'payment_method' => $data['payment_method'],
            'coupon_id' => $coupon?->id,
            'billing_address_id' => $data['billing_address_id'] ?? $data['shipping_address_id'] ?? null,
            'shipping_address_id' => $data['shipping_address_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'placed_at' => now(),
        ]);

        foreach ($byAgent as $agentId => $agentLines) {
            $agentSub = collect($agentLines)->sum('subtotal');
            $agentDisc = collect($agentLines)->sum('discount');
            $agentComm = collect($agentLines)->sum('commission');
            $agentEarn = collect($agentLines)->sum('agent_earning');

            $agentOrder = AgentOrder::create([
                'order_id' => $order->id,
                'agent_id' => $agentId,
                'subtotal' => $agentSub,
                'discount' => $agentDisc,
                'commission' => $agentComm,
                'delivery_fee' => 0,
                'total' => $agentSub - $agentDisc,
                'agent_earning' => $agentEarn,
                'status' => 'pending',
            ]);

            foreach ($agentLines as $l) {
                /** @var Listing $listing */
                $listing = $l['listing'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'agent_order_id' => $agentOrder->id,
                    'agent_id' => $agentId,
                    'listing_id' => $listing->id,
                    'product_name' => $listing->name,
                    'sku' => $listing->sku,
                    'agent_name' => $listing->agent->business_name,
                    'selected_options' => $l['selected_options'],
                    'unit_price' => $l['unit_price'],
                    'quantity' => $l['quantity'],
                    'discount' => $l['discount'],
                    'tax' => $l['tax'],
                    'subtotal' => $l['subtotal'],
                    'total' => $l['subtotal'] - $l['discount'],
                    'commission' => $l['commission'],
                    'agent_earning' => $l['agent_earning'],
                ]);

                Commission::create([
                    'order_id' => $order->id,
                    'agent_order_id' => $agentOrder->id,
                    'agent_id' => $agentId,
                    'commission_base' => $l['subtotal'] - $l['discount'],
                    'commission_rate' => $l['subtotal'] > 0 ? round($l['commission'] / ($l['subtotal'] - $l['discount'] ?: 1) * 100, 3) : 0,
                    'commission_amount' => $l['commission'],
                    'agent_earning' => $l['agent_earning'],
                    'status' => 'pending',
                ]);

                if ($listing->type !== 'service') {
                    Listing::whereKey($listing->id)->decrement('quantity', $l['quantity']);
                }
            }
        }

        OrderStatusHistory::create(['order_id' => $order->id, 'status' => 'pending', 'note' => 'Order placed', 'changed_by' => $customer->id]);

        if ($coupon) {
            CouponUsage::create([
                'coupon_id' => $coupon->id,
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'discount_amount' => $totalDiscount,
                'used_at' => now(),
            ]);
            $coupon->increment('times_used');
        }

        // ---- payment ----
        $result = ['order' => $order->fresh(), 'payment' => null, 'checkout_url' => null, 'instructions' => null];

        if ($data['payment_method'] === 'wallet') {
            $wallet = app(WalletService::class)->walletFor($customer);
            $payment = $this->payments->chargeWalletForOrder($customer, $wallet, $order);
            $order->update(['payment_status' => 'successful']);
            $result['order'] = $order->fresh();
            $result['payment'] = $payment;
        } elseif ($data['payment_method'] === 'pay_on_delivery') {
            $payment = $this->payments->create($customer, $data['payment_method'], 'order', $order, $total);
            $order->update(['order_status' => 'confirmed']);
            OrderStatusHistory::create(['order_id' => $order->id, 'status' => 'confirmed', 'note' => 'Pay on delivery', 'changed_by' => $customer->id]);
            $result['order'] = $order->fresh();
            $result['payment'] = $payment;
        } else {
            $payment = $this->payments->create($customer, $data['payment_method'], 'order', $order, $total);
            $payment->update(['meta' => array_merge($payment->meta ?? [], ['return_url' => url('/payments/return')])]);
            $init = $this->payments->initialize($payment);
            $result['checkout_url'] = $init['checkout_url'] ?? null;
            $result['instructions'] = $init['instructions'] ?? null;
            $result['payment'] = $payment->fresh();
        }

        DB::afterCommit(function () use ($order) {
            app(OrderNotificationService::class)->orderPlaced($order->fresh());
        });

        return $result;
    }

    /**
     * @return array<int,array{listing:Listing,quantity:int,unit_price:float,subtotal:float}>
     */
    private function resolveLines(array $items, bool $lock): array
    {
        $out = [];
        foreach ($items as $item) {
            $id = (int) ($item['listing_id'] ?? 0);
            $qty = max(1, (int) ($item['quantity'] ?? 1));

            $query = Listing::published()->with(['agent:id,business_name', 'subscription.package:id,commission_rate']);
            if ($lock) {
                $query->lockForUpdate();
            }
            $listing = $query->find($id);
            if (! $listing) {
                continue;
            }
            if ($lock && $listing->type !== 'service' && $listing->quantity < $qty) {
                throw ValidationException::withMessages([
                    'items' => ["Only {$listing->quantity} left of \"{$listing->name}\"."],
                ]);
            }

            $selectedOptions = array_filter([
                'size' => is_string($item['selected_options']['size'] ?? null) ? $item['selected_options']['size'] : null,
                'color' => is_string($item['selected_options']['color'] ?? null) ? $item['selected_options']['color'] : null,
            ]);
            foreach ($selectedOptions as $option => $value) {
                $availableOptions = $listing->{$option.'s'} ?? [];
                if ($listing->type === 'service' || ! in_array($value, $availableOptions, true)) {
                    throw ValidationException::withMessages([
                        'items' => ["The selected {$option} is no longer available for \"{$listing->name}\"."],
                    ]);
                }
            }

            $unit = $listing->effectivePrice();
            $out[] = [
                'listing' => $listing,
                'quantity' => $qty,
                'selected_options' => $selectedOptions,
                'unit_price' => $unit,
                'subtotal' => round($unit * $qty, 2),
            ];
        }

        return $out;
    }
}
