<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Validation\ValidationException;

/**
 * Guards what an agent is permitted to configure on a coupon (spec §51 — agent
 * coupons only apply to the agent's listings and cannot exceed platform caps).
 */
class CouponService
{
    public function assertAgentAllowed(Agent $agent, array $data): void
    {
        $maxPct = (float) config('payments.coupons.agent_max_percentage');
        $maxFixed = (float) config('payments.coupons.agent_max_fixed');

        if ($data['discount_type'] === 'percentage' && $data['discount_value'] > $maxPct) {
            throw ValidationException::withMessages([
                'discount_value' => ["Percentage discount cannot exceed {$maxPct}%."],
            ]);
        }

        if ($data['discount_type'] === 'fixed' && $data['discount_value'] > $maxFixed) {
            throw ValidationException::withMessages([
                'discount_value' => ["Fixed discount cannot exceed ₦{$maxFixed}."],
            ]);
        }
    }
}
