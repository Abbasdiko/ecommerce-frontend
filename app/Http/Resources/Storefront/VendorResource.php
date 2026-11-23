<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class VendorResource extends JsonResource
{
    public function toArray($request): array
    {
        $words = preg_split('/\s+/', trim($this->business_name)) ?: [];
        $initials = strtoupper(Str::substr($words[0] ?? '', 0, 1) . Str::substr($words[1] ?? '', 0, 1));

        return [
            'id' => (string) $this->id,
            'slug' => $this->slug,
            'name' => $this->business_name,
            'initials' => $initials ?: strtoupper(Str::substr($this->business_name, 0, 2)),
            'rating' => round((float) ($this->reviews_avg_rating ?? 0), 1),
            'reviews' => (int) ($this->reviews_count ?? 0),
            'productCount' => (int) ($this->product_count ?? $this->listings_count ?? 0),
            'location' => $this->state ?? 'Nigeria',
            'about' => $this->about ?? $this->description ?? '',
            'verified' => $this->verification_status === 'verified',
            'followers' => (int) $this->followers,
        ];
    }
}
