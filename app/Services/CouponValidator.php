<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\User;

class CouponValidator
{
    /**
     * @param  array<int,array{listing:\App\Models\Listing,quantity:int,subtotal:float}>  $lines
     * @return array{valid: bool, reason: ?string, coupon: ?Coupon, discount: float, eligible_subtotal: float}
     */
    public function evaluate(?string $code, User $customer, array $lines): array
    {
        if (! $code) {
            return $this->fail('No coupon supplied.');
        }

        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();
        if (! $coupon) {
            return $this->fail('Coupon not found.');
        }
        if (! $coupon->isLive()) {
            return $this->fail('This coupon is not currently active.');
        }

        $eligible = collect($lines);
        if ($coupon->owner_type === 'agent') {
            $eligible = $eligible->filter(fn ($l) => $l['listing']->agent_id === (int) $coupon->owner_id);
            if ($eligible->isEmpty()) {
                return $this->fail("This coupon only applies to that agent's items.");
            }
        }

        $eligibleSubtotal = (float) $eligible->sum('subtotal');

        if ($coupon->minimum_order_amount > 0 && $eligibleSubtotal < (float) $coupon->minimum_order_amount) {
            return $this->fail('Order does not meet the minimum for this coupon (₦' . number_format((float) $coupon->minimum_order_amount) . ').');
        }

        $usedByCustomer = CouponUsage::where('coupon_id', $coupon->id)->where('customer_id', $customer->id)->count();
        if ($coupon->usage_per_customer && $usedByCustomer >= $coupon->usage_per_customer) {
            return $this->fail('You have already used this coupon.');
        }

        $discount = $coupon->discount_type === 'percentage'
            ? $eligibleSubtotal * (float) $coupon->discount_value / 100
            : (float) $coupon->discount_value;

        if ($coupon->maximum_discount) {
            $discount = min($discount, (float) $coupon->maximum_discount);
        }
        $discount = round(min($discount, $eligibleSubtotal), 2);

        return [
            'valid' => true,
            'reason' => null,
            'coupon' => $coupon,
            'discount' => $discount,
            'eligible_subtotal' => $eligibleSubtotal,
        ];
    }

    private function fail(string $reason): array
    {
        return ['valid' => false, 'reason' => $reason, 'coupon' => null, 'discount' => 0.0, 'eligible_subtotal' => 0.0];
    }
}
