<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\ListingResource;
use App\Http\Resources\Storefront\VendorResource;
use App\Models\Agent;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Service;
use App\Models\Review;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    use ApiResponse;

    /** Agent query with rating/review aggregates pulled from the agent's listings. */
    private function vendorBase(): Builder
    {
        return Agent::query()
            ->where('status', 'active')
            ->withCount(['listings as product_count' => fn ($q) => $q->published()])
            ->addSelect([
                'reviews_count' => Review::selectRaw('COUNT(*)')
                    ->whereIn('subject_id', fn ($q) => $q->select('id')->from('listings')->whereColumn('listings.agent_id', 'agents.id'))
                    ->where('subject_type', 'listing')->where('status', 'published'),
                'reviews_avg_rating' => Review::selectRaw('COALESCE(AVG(rating), 0)')
                    ->whereIn('subject_id', fn ($q) => $q->select('id')->from('listings')->whereColumn('listings.agent_id', 'agents.id'))
                    ->where('subject_type', 'listing')->where('status', 'published'),
            ]);
    }

    private function listingBase()
    {
        return Listing::query()
            ->published()
            ->with(['agent:id,slug,business_name,state,verification_status', 'category:id,name,slug,parent_id', 'category.parent:id,slug', 'service:id,name,slug', 'images'])
            ->withCount('reviews')
            ->withAvg('reviews as reviews_avg_rating', 'rating');
    }

    public function home(Request $request)
    {
        $card = fn ($q, $n) => ListingResource::collection($q->take($n)->get());

        $vendors = $this->vendorBase()->orderByDesc('product_count')->take(8)->get();

        return $this->ok([
            'hero' => $card($this->listingBase()->latest('published_at'), 6),
            'hot_deals' => $card($this->listingBase()->whereNotNull('sale_price')->orderByDesc('sales_count'), 10),
            'best_sellers' => $card($this->listingBase()->orderByDesc('sales_count'), 10),
            'new_arrivals' => $card($this->listingBase()->latest('published_at'), 10),
            'flash_deals' => $card($this->listingBase()->whereNotNull('sale_price'), 10),
            'todays_deals' => $card($this->listingBase()->whereNotNull('sale_price')->latest('published_at'), 10),
            'featured' => $card($this->listingBase()->where('featured', true), 10),
            'recommendations' => $card($this->listingBase()->inRandomOrder(), 10),
            'departments' => $this->departmentTree(),
            'services' => [
                'categories' => Service::where('status', 'active')->get()->map(fn ($s) => $this->serviceShape($s)),
                'listings' => $card($this->listingBase()->where('type', 'service'), 8),
            ],
            'vendors' => $vendors->map(fn ($v) => [
                'vendor' => new VendorResource($v),
                'listings' => ListingResource::collection(
                    $this->listingBase()->where('agent_id', $v->id)->take(8)->get()
                ),
            ]),
            'cached_at' => now()->toIso8601String(),
        ]);
    }

    public function listings(Request $request)
    {
        $q = $this->listingBase();

        // Cart / wishlist hydration by id list.
        if ($request->filled('ids')) {
            $ids = collect(explode(',', $request->ids))->filter()->map('intval')->take(100);

            return ListingResource::collection($q->whereIn('id', $ids)->get());
        }

        $q->when($request->search, fn ($qq, $s) => $qq->where(fn ($w) => $w
            ->where('name', 'like', "%$s%")
            ->orWhere('description', 'like', "%$s%")
            ->orWhere('sku', 'like', "%$s%")))
            ->when($request->type, fn ($qq, $t) => $qq->where('type', $t))
            ->when($request->department, fn ($qq, $slug) => $qq->whereHas('category', fn ($c) => $c
                ->where('slug', $slug)->orWhereHas('parent', fn ($p) => $p->where('slug', $slug))))
            ->when($request->category, fn ($qq, $slug) => $qq->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($request->service, fn ($qq, $slug) => $qq->whereHas('service', fn ($c) => $c->where('slug', $slug)))
            ->when($request->vendor, fn ($qq, $slug) => $qq->whereHas('agent', fn ($a) => $a->where('slug', $slug)))
            ->when($request->min_price, fn ($qq, $v) => $qq->where('price', '>=', $v))
            ->when($request->max_price, fn ($qq, $v) => $qq->where('price', '<=', $v))
            ->when($request->boolean('on_sale'), fn ($qq) => $qq->whereNotNull('sale_price'))
            ->when($request->boolean('featured'), fn ($qq) => $qq->where('featured', true));

        match ($request->sort) {
            'price_asc' => $q->orderBy('price'),
            'price_desc' => $q->orderByDesc('price'),
            'rating' => $q->orderByDesc('reviews_avg_rating'),
            'newest' => $q->latest('published_at'),
            'bestselling' => $q->orderByDesc('sales_count'),
            default => $q->latest('published_at'),
        };

        $perPage = (int) min(48, max(1, (int) $request->input('per_page', 24)));

        return ListingResource::collection($q->paginate($perPage));
    }

    public function listing(Request $request, string $slug)
    {
        $listing = $this->listingBase()->where('slug', $slug)->firstOrFail();

        $related = $this->listingBase()
            ->where('id', '!=', $listing->id)
            ->where(fn ($w) => $w->where('category_id', $listing->category_id)->orWhere('agent_id', $listing->agent_id))
            ->take(8)->get();

        return $this->ok([
            'listing' => new ListingResource($listing),
            'related' => ListingResource::collection($related),
            'reviews' => $listing->reviews()->where('status', 'published')
                ->with('customer:id,first_name,last_name')->latest()->take(10)->get()
                ->map(fn ($r) => [
                    'rating' => $r->rating,
                    'title' => $r->title,
                    'body' => $r->body,
                    'author' => trim(optional($r->customer)->first_name . ' ' . Str::substr(optional($r->customer)->last_name ?? '', 0, 1) . '.'),
                    'created_at' => $r->created_at->toIso8601String(),
                    'response' => $r->response,
                ]),
        ]);
    }

    public function vendors(Request $request)
    {
        $vendors = $this->vendorBase()
            ->when($request->search, fn ($q, $s) => $q->where('business_name', 'like', "%$s%"))
            ->orderByDesc('product_count')
            ->paginate((int) min(48, (int) $request->input('per_page', 24)));

        return VendorResource::collection($vendors);
    }

    public function vendor(Request $request, string $slug)
    {
        $vendor = $this->vendorBase()->where('slug', $slug)->firstOrFail();

        return $this->ok([
            'vendor' => new VendorResource($vendor),
            'listings' => ListingResource::collection(
                $this->listingBase()->where('agent_id', $vendor->id)->take(60)->get()
            ),
        ]);
    }

    public function departments()
    {
        return $this->ok($this->departmentTree());
    }

    private function departmentTree(): array
    {
        return Category::whereNull('parent_id')->with('children:id,parent_id,name,slug')
            ->orderBy('sort_order')->get()
            ->map(fn ($d) => [
                'id' => (string) $d->id,
                'slug' => $d->slug,
                'name' => $d->name,
                'icon' => $d->icon ?? 'Package',
                'children' => $d->children->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug])->values(),
            ])->values()->all();
    }

    private function serviceShape(Service $s): array
    {
        return [
            'id' => (string) $s->id,
            'slug' => $s->slug,
            'name' => $s->name,
            'icon' => $s->icon ?? 'Wrench',
            'tagline' => $s->tagline ?? $s->description ?? '',
            'listings' => Listing::published()->where('service_id', $s->id)->count(),
        ];
    }
}
