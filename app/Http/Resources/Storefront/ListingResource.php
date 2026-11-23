<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Maps a backend Listing onto the shape the React storefront expects
 * (src/lib/marketplace/types.ts -> Listing).
 */
class ListingResource extends JsonResource
{
    public function toArray($request): array
    {
        $price = (float) $this->price;
        $sale = $this->sale_price !== null ? (float) $this->sale_price : null;
        $rating = round((float) ($this->reviews_avg_rating ?? 0), 1);

        $badges = [];
        if ($sale) {
            $badges[] = 'Sale';
        }
        if ($this->featured) {
            $badges[] = 'Featured';
        }
        if ($this->agent && $this->agent->verification_status === 'verified') {
            $badges[] = 'Verified agent';
        }

        $department = optional(optional($this->category)->parent)->slug
            ?? optional($this->category)->slug
            ?? optional($this->service)->slug
            ?? 'general';

        return [
            'id' => (string) $this->id,
            'slug' => $this->slug,
            'type' => $this->type,
            'name' => $this->name,
            'shortDescription' => Str::limit(strip_tags((string) $this->description), 110),
            'description' => (string) $this->description,
            'image' => $this->primaryImage(),
            'department' => $department,
            'category' => optional($this->category)->name ?? optional($this->service)->name ?? 'General',
            'serviceCategory' => $this->type === 'service' ? optional($this->service)->slug : null,
            'brand' => $this->brand ?? optional($this->service)->name ?? '',
            'sku' => $this->sku ?? ('SKU-'.$this->id),
            'sizes' => $this->sizes ?? [],
            'colors' => $this->colors ?? [],
            'images' => $this->relationLoaded('images')
                ? $this->images->map(fn ($image) => asset('storage/'.$image->path))->values()
                : [],
            'vendorId' => (string) $this->agent_id,
            'vendorSlug' => optional($this->agent)->slug,
            'vendorName' => optional($this->agent)->business_name,
            'base_price' => $sale ?? $price,
            'compare_at_price' => $sale ? $price : null,
            'rating' => (float) $rating,
            'reviews' => (int) ($this->reviews_count ?? 0),
            'stock' => (int) $this->quantity,
            'stock_status' => $this->stock_status,
            'condition' => Str::title($this->condition ?? 'new'),
            'location' => optional($this->agent)->state ?? 'Nigeria',
            'is_flash_deal' => (bool) $sale,
            'is_featured' => (bool) $this->featured,
            'is_todays_deal' => (bool) $sale,
            'completed_sales' => (int) $this->sales_count,
            'published_at' => optional($this->published_at)->toIso8601String(),
            'deal_ends_at' => optional($this->deal_ends_at)->toIso8601String(),
            'badges' => $badges,
            'specs' => $this->when($this->type === 'product', [
                ['label' => 'Condition', 'value' => Str::title($this->condition)],
                ['label' => 'SKU', 'value' => $this->sku ?? '—'],
                ['label' => 'Sizes', 'value' => implode(', ', $this->sizes ?? []) ?: '—'],
                ['label' => 'Colors', 'value' => implode(', ', $this->colors ?? []) ?: '—'],
            ], []),
            'availability' => $this->type === 'service' ? 'Available on request' : null,
            'bookingCta' => $this->type === 'service' ? 'Request Service' : null,
        ];
    }

    private function primaryImage(): string
    {
        if ($this->relationLoaded('images') && $this->images->isNotEmpty()) {
            $img = $this->images->firstWhere('is_primary', true) ?? $this->images->first();

            return asset('storage/'.$img->path);
        }

        // Deterministic placeholder so cards look populated in the demo.
        $seed = urlencode($this->slug ?: (string) $this->id);

        return "https://picsum.photos/seed/{$seed}/640/480";
    }
}
