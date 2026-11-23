<?php

namespace App\Services;

use App\Models\CommissionRule;
use App\Models\Setting;

/**
 * Resolves the commission rate for a sale using scope priority (spec §55):
 * agent → category → service → global rule → platform default setting.
 */
class CommissionResolver
{
    public function rateFor(?int $agentId, ?int $serviceId, ?int $categoryId, ?float $packageFallback = null): array
    {
        if (! PlatformSettings::chargesSaleCommission()) {
            return ['type' => 'fixed', 'value' => 0, 'source' => 'billing mode'];
        }

        $rules = CommissionRule::active()->get();

        $candidates = [
            $rules->first(fn ($r) => $r->scope_type === 'agent' && (int) $r->scope_id === $agentId),
            $rules->first(fn ($r) => $r->scope_type === 'category' && (int) $r->scope_id === $categoryId),
            $rules->first(fn ($r) => $r->scope_type === 'service' && (int) $r->scope_id === $serviceId),
            $rules->first(fn ($r) => $r->scope_type === 'global'),
        ];

        foreach ($candidates as $rule) {
            if ($rule) {
                return [
                    'type' => $rule->commission_type,
                    'value' => (float) $rule->commission_value,
                    'source' => $rule->scope_type . ($rule->scope_id ? " #{$rule->scope_id}" : ''),
                ];
            }
        }

        if (PlatformSettings::requiresListingSubscription() && $packageFallback !== null) {
            return ['type' => 'percentage', 'value' => $packageFallback, 'source' => 'package'];
        }

        return [
            'type' => 'percentage',
            'value' => (float) (Setting::value('commission.default_rate') ?? 10),
            'source' => 'platform default',
        ];
    }

    public function compute(float $base, array $rate): float
    {
        return $rate['type'] === 'fixed'
            ? min($rate['value'], $base)
            : round($base * $rate['value'] / 100, 2);
    }
}
