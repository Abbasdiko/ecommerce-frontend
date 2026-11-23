<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ServicePackageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'duration_days' => $this->duration_days,
            'listing_limit' => $this->listing_limit,
            'featured_listing_limit' => $this->featured_listing_limit,
            'commission_rate' => (float) $this->commission_rate,
            'status' => $this->status,
            'service' => new ServiceResource($this->whenLoaded('service')),
        ];
    }
}
