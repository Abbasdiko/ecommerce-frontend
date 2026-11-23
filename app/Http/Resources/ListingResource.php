<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ListingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'agent_id' => $this->agent_id,
            'service_id' => $this->service_id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'sku' => $this->sku,
            'sizes' => $this->sizes ?? [],
            'colors' => $this->colors ?? [],
            'price' => (float) $this->price,
            'sale_price' => $this->sale_price !== null ? (float) $this->sale_price : null,
            'quantity' => $this->quantity,
            'stock_status' => $this->stock_status,
            'condition' => $this->condition,
            'type' => $this->type,
            'status' => $this->status,
            'approval_status' => $this->approval_status,
            'rejection_reason' => $this->rejection_reason,
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
            'sales_count' => (int) $this->sales_count,
            'sales_summary' => $this->when(isset($this->sales_summary), $this->sales_summary),
            'reviews_summary' => $this->when(isset($this->reviews_summary), $this->reviews_summary),
            'agent' => new AgentResource($this->whenLoaded('agent')),
            'service' => new ServiceResource($this->whenLoaded('service')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'path' => $image->path,
                'url' => asset('storage/'.$image->path),
                'is_primary' => $image->is_primary,
                'sort_order' => $image->sort_order,
            ])),
        ];
    }
}
