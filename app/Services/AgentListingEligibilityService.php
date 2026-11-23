<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentSubscription;
use App\Models\Service;

/**
 * Central authority for "can this agent list?" business rules (spec §3, §29, §95).
 * The API must call this — never rely on the frontend hiding a button.
 */
class AgentListingEligibilityService
{
    /**
     * @return array{allowed: bool, reason: ?string, subscription: ?AgentSubscription}
     */
    public function evaluate(Agent $agent, Service $service): array
    {
        if ($agent->status !== 'active') {
            return $this->deny('Agent account is not active.');
        }

        if ($agent->verification_status === 'rejected') {
            return $this->deny('Agent verification was rejected.');
        }

        if ($service->status !== 'active') {
            return $this->deny('This service is not currently available.');
        }

        if (! PlatformSettings::requiresListingSubscription()) {
            return ['allowed' => true, 'reason' => null, 'subscription' => null];
        }

        $subscription = $agent->subscriptions()
            ->where('service_id', $service->id)
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->orderByDesc('end_date')
            ->first();

        if (! $subscription) {
            return $this->deny('No active subscription for this service.');
        }

        if ($subscription->remainingListingSlots() < 1) {
            return $this->deny('Listing limit for the current subscription has been reached.');
        }

        return ['allowed' => true, 'reason' => null, 'subscription' => $subscription];
    }

    public function canCreateListing(Agent $agent, Service $service): bool
    {
        return $this->evaluate($agent, $service)['allowed'];
    }

    private function deny(string $reason): array
    {
        return ['allowed' => false, 'reason' => $reason, 'subscription' => null];
    }
}
