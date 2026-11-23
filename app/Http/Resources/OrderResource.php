<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'order_number' => $this->order_number,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'delivery_fee' => (float) $this->delivery_fee,
            'tax' => (float) $this->tax,
            'total' => (float) $this->total,
            'currency' => $this->currency,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'order_status' => $this->order_status,
            'placed_at' => $this->placed_at,
            'created_at' => $this->created_at,
            'items_count' => $this->whenCounted('items'),
            'can_request_cancellation' => in_array($this->order_status, \App\Models\Order::CUSTOMER_CANCELLABLE, true)
                && $this->cancellationRequests->isEmpty(),
            'can_request_return' => in_array($this->order_status, \App\Models\Order::RETURNABLE, true),
            'agent_orders' => $this->whenLoaded('agentOrders', fn () => $this->agentOrders->map(fn ($ao) => [
                'id' => $ao->id,
                'reference' => $ao->reference,
                'agent_id' => $ao->agent_id,
                'agent_name' => optional($ao->agent)->business_name,
                'status' => $ao->status,
                'tracking_number' => $ao->tracking_number,
                'subtotal' => (float) $ao->subtotal,
                'total' => (float) $ao->total,
                'items' => $ao->items->map(fn ($i) => [
                    'id' => $i->id,
                    'listing_id' => $i->listing_id,
                    'product_name' => $i->product_name,
                    'sku' => $i->sku,
                    'selected_options' => $i->selected_options ?? [],
                    'unit_price' => (float) $i->unit_price,
                    'quantity' => $i->quantity,
                    'total' => (float) $i->total,
                    'reviewed' => (bool) $i->reviewed,
                ]),
            ])),
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($h) => [
                'status' => $h->status,
                'note' => $h->note,
                'at' => $h->created_at,
            ])),
            'shipping_address' => $this->whenLoaded('shippingAddress'),
            'billing_address' => $this->whenLoaded('billingAddress'),
            'cancellation_requests' => $this->whenLoaded('cancellationRequests'),
            'return_requests' => $this->whenLoaded('returnRequests'),
        ];
    }
}
