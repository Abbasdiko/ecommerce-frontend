<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AgentSubscriptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'subscription_reference' => $this->subscription_reference,
            'agent_id' => $this->agent_id,
            'service_id' => $this->service_id,
            'package_id' => $this->package_id,
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_reference' => $this->payment_reference,
            'auto_renew' => $this->auto_renew,
            'listings_used' => $this->listings_used,
            'listing_limit' => $this->whenLoaded('package', fn () => $this->package->listing_limit),
            'remaining_listing_slots' => $this->whenLoaded('package', fn () => $this->remainingListingSlots()),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'is_active' => $this->isActive(),
            'created_at' => $this->created_at,
            'service' => new ServiceResource($this->whenLoaded('service')),
            'package' => new ServicePackageResource($this->whenLoaded('package')),
            'agent' => new AgentResource($this->whenLoaded('agent')),
        ];
    }
}
