<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AgentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'business_name' => $this->business_name,
            'slug' => $this->slug,
            'business_registration_number' => $this->business_registration_number,
            'description' => $this->description,
            'logo' => $this->logo,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'state' => $this->state,
            'lga' => $this->lga,
            'country' => $this->country,
            'status' => $this->status,
            'verification_status' => $this->verification_status,
            'rejection_reason' => $this->rejection_reason,
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'active_subscriptions_count' => $this->when(isset($this->active_subscriptions_count), $this->active_subscriptions_count),
            'listings_count' => $this->when(isset($this->listings_count), $this->listings_count),
            'paid_sales_count' => $this->when(isset($this->paid_sales_count), $this->paid_sales_count),
            'sales_total' => $this->when(isset($this->sales_total), (float) $this->sales_total),
            'company_earnings' => $this->when(isset($this->company_earnings), (float) $this->company_earnings),
            'sales' => $this->whenLoaded('paidAgentOrders', fn () => $this->paidAgentOrders->map(fn ($sale) => [
                'id' => $sale->id,
                'reference' => $sale->reference,
                'total' => (float) $sale->total,
                'commission' => (float) $sale->commission,
                'agent_earning' => (float) $sale->agent_earning,
                'status' => $sale->status,
                'created_at' => $sale->created_at,
                'order' => $sale->order,
                'items' => $sale->items,
            ])),
            'subscriptions' => AgentSubscriptionResource::collection($this->whenLoaded('subscriptions')),
            'documents' => $this->whenLoaded('documents'),
        ];
    }
}
