<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ListingResource;
use App\Models\Agent;
use App\Models\Listing;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\Service;
use App\Models\Setting;
use App\Services\AgentListingEligibilityService;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ListingController extends Controller
{
    use ApiResponse;

    public function __construct(private AgentListingEligibilityService $eligibility) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Listing::class);
        $user = $request->user();

        $query = Listing::query()
            ->with(['agent:id,business_name', 'service:id,name', 'category:id,name', 'images'])
            ->latest();

        // Agent data isolation (spec §139): an agent only ever sees their own listings,
        // regardless of what permissions the agent role carries.
        if ($user->hasRole('agent')) {
            abort_unless($user->agent, 403, 'Complete your business profile first.');
            $query->where('agent_id', $user->agent->id);
        } else {
            $query->when($request->agent_id, fn ($q, $id) => $q->where('agent_id', $id));
        }

        $query->when($request->approval_status, fn ($q, $s) => $q->where('approval_status', $s))
            ->when($request->service_id, fn ($q, $id) => $q->where('service_id', $id))
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%$s%"));

        return ListingResource::collection($query->paginate($this->perPage($request)));
    }

    public function show(Request $request, Listing $listing)
    {
        $this->authorize('view', $listing);

        $listing->load(['agent', 'service', 'category', 'images']);
        $listing->setAttribute('sales_summary', $this->salesSummary($listing));
        $listing->setAttribute('reviews_summary', [
            'count' => $listing->reviews()->where('status', 'published')->count(),
            'average_rating' => round((float) $listing->reviews()->where('status', 'published')->avg('rating'), 2),
        ]);

        return $this->ok(new ListingResource($listing));
    }

    /**
     * Create a listing. Backend enforces the subscription/eligibility rule (spec §3, §29, §89).
     */
    public function store(Request $request)
    {
        $this->authorize('create', Listing::class);
        $user = $request->user();
        $isAgent = $user->hasRole('agent');

        $data = $request->validate([
            'agent_id' => $isAgent ? ['prohibited'] : ['required', 'exists:agents,id'],
            'service_id' => ['required', 'exists:services,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sku' => ['nullable', 'string', 'max:80'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'quantity' => ['required', 'integer', 'min:0'],
            'condition' => ['nullable', Rule::in(['new', 'used', 'refurbished'])],
            'type' => ['nullable', Rule::in(['product', 'service'])],
            'sizes' => ['nullable', 'array', 'max:30'],
            'sizes.*' => ['string', 'max:80', Rule::in(Setting::value('catalog.product_sizes', ['XS', 'S', 'M', 'L', 'XL', 'XXL']))],
            'colors' => ['nullable', 'array', 'max:30'],
            'colors.*' => ['string', 'max:80', Rule::in(Setting::value('catalog.product_colors', ['Black', 'White', 'Red', 'Blue', 'Green']))],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $agent = $isAgent ? $user->agent : Agent::findOrFail($data['agent_id']);
        abort_unless($agent, 403, 'Complete your business profile first.');
        abort_if(! $isAgent && $agent->status !== 'active', 422, 'Select an active vendor.');
        $service = Service::findOrFail($data['service_id']);
        $verdict = $isAgent ? $this->eligibility->evaluate($agent, $service) : ['allowed' => true, 'subscription' => null];

        if (! $verdict['allowed']) {
            // spec §89 / §117: must be 403, not just a hidden button
            return $this->fail($verdict['reason'], ['eligibility' => $verdict['reason']], 403);
        }

        $subscription = $verdict['subscription'];

        $listing = DB::transaction(function () use ($data, $agent, $subscription, $request) {
            $attributes = collect($data)->except(['images', 'agent_id'])->all();
            $attributes['sizes'] = array_values(array_filter($data['sizes'] ?? []));
            $attributes['colors'] = array_values(array_filter($data['colors'] ?? []));
            $listing = Listing::create($attributes + [
                'agent_id' => $agent->id,
                'subscription_id' => $subscription?->id,
                'status' => 'draft',
                'approval_status' => 'pending',
                'stock_status' => ($data['quantity'] ?? 0) > 0 ? 'in_stock' : 'out_of_stock',
            ]);

            $subscription?->increment('listings_used');
            $this->storeImages($listing, $request);

            if (! filled($listing->sku)) {
                $listing->update(['sku' => $this->generateSku()]);
            }

            return $listing;
        });

        AuditLogger::record('listing.created', $listing);

        return $this->created(new ListingResource($listing->load(['agent', 'service', 'category', 'images'])), 'Listing created (pending review)');
    }

    public function update(Request $request, Listing $listing)
    {
        $this->authorize('update', $listing);

        $data = $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sku' => ['nullable', 'string', 'max:80'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['sometimes', 'integer', 'min:0'],
            'condition' => ['sometimes', Rule::in(['new', 'used', 'refurbished'])],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'inactive'])],
            'sizes' => ['sometimes', 'nullable', 'array', 'max:30'],
            'sizes.*' => ['string', 'max:80', Rule::in(array_unique(array_merge(
                Setting::value('catalog.product_sizes', ['XS', 'S', 'M', 'L', 'XL', 'XXL']),
                $listing->sizes ?? [],
            )))],
            'colors' => ['sometimes', 'nullable', 'array', 'max:30'],
            'colors.*' => ['string', 'max:80', Rule::in(array_unique(array_merge(
                Setting::value('catalog.product_colors', ['Black', 'White', 'Red', 'Blue', 'Green']),
                $listing->colors ?? [],
            )))],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'images_to_remove' => ['nullable', 'array'],
            'images_to_remove.*' => ['integer', Rule::exists('listing_images', 'id')],
            'sizes_provided' => ['sometimes', 'boolean'],
            'colors_provided' => ['sometimes', 'boolean'],
        ]);
        $removeIds = $data['images_to_remove'] ?? [];
        $ownedRemovals = $listing->images()->whereIn('id', $removeIds)->count();
        $remainingImages = $listing->images()->count() - $ownedRemovals;
        if ($remainingImages + count($request->file('images', [])) > 8) {
            return $this->fail('A listing can have at most 8 images.', ['images' => 'Remove an existing image before adding more.'], 422);
        }

        $old = $listing->only(['name', 'price', 'quantity', 'status']);

        // An agent editing key fields sends the listing back for review.
        if ($request->user()->hasRole('agent')
            && $listing->approval_status !== 'pending'
            && array_intersect(array_keys($data), ['name', 'description', 'price', 'category_id'])) {
            $data['approval_status'] = 'pending';
            $data['rejection_reason'] = null;
            $data['published_at'] = null;
            if (($data['status'] ?? $listing->status) !== 'active') {
                $data['status'] = 'draft';
            }
        }

        if (array_key_exists('quantity', $data)) {
            $data['stock_status'] = $data['quantity'] > 0 ? 'in_stock' : 'out_of_stock';
        }

        DB::transaction(function () use ($data, $listing, $request) {
            $attributes = collect($data)->except(['images', 'images_to_remove', 'sizes_provided', 'colors_provided'])->all();
            if (array_key_exists('sizes', $data) || ($data['sizes_provided'] ?? false)) {
                $attributes['sizes'] = array_values(array_filter($attributes['sizes'] ?? []));
            }
            if (array_key_exists('colors', $data) || ($data['colors_provided'] ?? false)) {
                $attributes['colors'] = array_values(array_filter($attributes['colors'] ?? []));
            }
            if (array_key_exists('sku', $attributes) && ! filled($attributes['sku'])) {
                $attributes['sku'] = $this->generateSku();
            } elseif (! filled($listing->sku)) {
                $attributes['sku'] = $this->generateSku();
            }

            $listing->update($attributes);

            foreach ($data['images_to_remove'] ?? [] as $imageId) {
                $image = $listing->images()->find($imageId);
                abort_unless($image, 422, 'The selected image does not belong to this listing.');
                Storage::disk('public')->delete($image->path);
                $image->delete();
            }

            if (! $listing->images()->where('is_primary', true)->exists()) {
                $listing->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
            }

            $this->storeImages($listing, $request);
        });
        AuditLogger::record('listing.updated', $listing, $old, $listing->only(['name', 'price', 'quantity', 'status']));

        return $this->ok(new ListingResource($listing->fresh(['agent', 'service', 'category', 'images'])), 'Listing updated');
    }

    public function reviews(Request $request, Listing $listing)
    {
        $this->authorize('view', $listing);

        $query = $listing->reviews()->with('customer:id,first_name,last_name')->latest();
        if ($request->user()->hasRole('agent')) {
            $query->where('status', 'published');
        }

        return $this->ok([
            'summary' => [
                'count' => $listing->reviews()->where('status', 'published')->count(),
                'average_rating' => round((float) $listing->reviews()->where('status', 'published')->avg('rating'), 2),
            ],
            'reviews' => $query->paginate($this->perPage($request)),
        ]);
    }

    public function updateReview(Request $request, Listing $listing, Review $review)
    {
        $this->authorizePermission($request, 'review.moderate');
        abort_unless($review->subject_type === 'listing' && $review->subject_id === $listing->id, 404);

        $data = $request->validate([
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(['published', 'rejected'])],
        ]);
        $old = $review->only(['rating', 'title', 'body', 'status']);
        $review->update($data);
        AuditLogger::record('review.moderated', $review, $old, $review->only(array_keys($data)));

        return $this->ok($review->fresh('customer:id,first_name,last_name'), 'Review updated');
    }

    public function destroyReview(Request $request, Listing $listing, Review $review)
    {
        $this->authorizePermission($request, 'review.moderate');
        abort_unless($review->subject_type === 'listing' && $review->subject_id === $listing->id, 404);

        $review->delete();
        AuditLogger::record('review.deleted', $review);

        return $this->ok(null, 'Review deleted');
    }

    public function destroy(Request $request, Listing $listing)
    {
        $this->authorize('delete', $listing);

        DB::transaction(function () use ($listing) {
            if ($listing->subscription && $listing->approval_status === 'approved') {
                $listing->subscription->decrement('listings_used');
            }
            $listing->delete();
        });

        AuditLogger::record('listing.deleted', $listing);

        return $this->ok(null, 'Listing deleted');
    }

    public function approve(Request $request, Listing $listing)
    {
        $this->authorize('approve', $listing);

        $listing->update([
            'approval_status' => 'approved',
            'status' => 'active',
            'rejection_reason' => null,
            'reviewed_by' => $request->user()->id,
            'published_at' => now(),
        ]);

        AuditLogger::record('listing.approved', $listing);

        return $this->ok(new ListingResource($listing->fresh(['agent', 'service'])), 'Listing approved and published');
    }

    public function reject(Request $request, Listing $listing)
    {
        $this->authorize('reject', $listing);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $listing->update([
            'approval_status' => 'rejected',
            'status' => 'inactive',
            'rejection_reason' => $data['reason'],
            'reviewed_by' => $request->user()->id,
            'published_at' => null,
        ]);

        AuditLogger::record('listing.rejected', $listing, [], ['reason' => $data['reason']]);

        return $this->ok(new ListingResource($listing->fresh(['agent', 'service'])), 'Listing rejected');
    }

    /**
     * Agent resubmits a rejected / draft listing for review without further edits (spec §30).
     */
    public function resubmit(Request $request, Listing $listing)
    {
        $this->authorize('update', $listing);
        abort_unless($request->user()->hasRole('agent'), 403);

        if (! in_array($listing->approval_status, ['rejected', 'pending'], true) && $listing->status !== 'draft') {
            return $this->fail('Only rejected or draft listings can be resubmitted.', null, 409);
        }

        // Re-check the agent is still eligible to list on this service.
        $verdict = $this->eligibility->evaluate($listing->agent, $listing->service);
        if (! $verdict['allowed']) {
            return $this->fail($verdict['reason'], ['eligibility' => $verdict['reason']], 403);
        }

        $listing->update([
            'approval_status' => 'pending',
            'status' => $listing->status === 'inactive' ? 'draft' : $listing->status,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'published_at' => null,
        ]);
        AuditLogger::record('listing.resubmitted', $listing);

        return $this->ok(new ListingResource($listing->fresh(['service', 'category'])), 'Listing resubmitted for review');
    }

    private function storeImages(Listing $listing, Request $request): void
    {
        foreach ($request->file('images', []) as $file) {
            $path = $file->store('listings/'.$listing->id, 'public');
            $listing->images()->create([
                'path' => $path,
                'is_primary' => ! $listing->images()->exists(),
                'sort_order' => (int) $listing->images()->max('sort_order') + 1,
            ]);
        }
    }

    private function generateSku(): string
    {
        do {
            $sku = 'SKU-'.Str::upper(Str::random(10));
        } while (Listing::withTrashed()->where('sku', $sku)->exists());

        return $sku;
    }

    private function salesSummary(Listing $listing): array
    {
        $sales = OrderItem::query()
            ->where('listing_id', $listing->id)
            ->whereHas('order', fn ($query) => $query->where('payment_status', 'successful'))
            ->selectRaw('COALESCE(SUM(quantity), 0) as units_sold')
            ->selectRaw('COALESCE(SUM(total), 0) as total_sales')
            ->selectRaw('COALESCE(SUM(commission), 0) as company_earnings')
            ->selectRaw('COALESCE(SUM(agent_earning), 0) as agent_earnings')
            ->first();

        return [
            'units_sold' => (int) $sales->units_sold,
            'total_sales' => (float) $sales->total_sales,
            'company_earnings' => (float) $sales->company_earnings,
            'agent_earnings' => (float) $sales->agent_earnings,
        ];
    }
}
