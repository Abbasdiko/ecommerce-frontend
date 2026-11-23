<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'service_type' => $this->service_type,
            'status' => $this->status,
            'requires_approval' => $this->requires_approval,
            'packages_count' => $this->whenCounted('packages'),
            'packages' => ServicePackageResource::collection($this->whenLoaded('packages')),
            'created_at' => $this->created_at,
        ];
    }
}
