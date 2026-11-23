<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Review;
use App\Models\User;

/**
 * Determines which purchases a customer may still review (spec §70 — verified reviews only).
 */
class ReviewEligibilityService
{
    /**
     * Order items from delivered orders that the customer has not reviewed yet,
     * plus the agents behind those items.
     */
    public function pending(User $customer): array
    {
        $items = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('customer_id', $customer->id)->where('order_status', 'delivered'))
            ->where('reviewed', 0)
            ->with(['listing:id,name,slug', 'agent:id,business_name', 'order:id,order_number'])
            ->get();

        $reviewedAgentIds = Review::where('customer_id', $customer->id)
            ->where('subject_type', 'agent')
            ->pluck('subject_id')
            ->all();

        $agents = $items
            ->pluck('agent')
            ->filter()
            ->unique('id')
            ->reject(fn ($agent) => in_array($agent->id, $reviewedAgentIds, true))
            ->values();

        return [
            'products' => $items->map(fn (OrderItem $i) => [
                'order_item_id' => $i->id,
                'order_id' => $i->order_id,
                'order_number' => $i->order?->order_number,
                'listing_id' => $i->listing_id,
                'product_name' => $i->product_name,
                'agent_id' => $i->agent_id,
                'agent_name' => $i->agent_name,
            ])->values(),
            'agents' => $agents->map(fn ($a) => [
                'agent_id' => $a->id,
                'business_name' => $a->business_name,
            ]),
        ];
    }

    public function hasPurchased(User $customer, string $subjectType, int $subjectId): bool
    {
        return OrderItem::whereHas('order', fn ($q) => $q->where('customer_id', $customer->id)->where('order_status', 'delivered'))
            ->when($subjectType === 'listing', fn ($q) => $q->where('listing_id', $subjectId))
            ->when($subjectType === 'agent', fn ($q) => $q->where('agent_id', $subjectId))
            ->exists();
    }
}
