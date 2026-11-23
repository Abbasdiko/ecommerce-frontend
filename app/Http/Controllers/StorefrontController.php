<?php

namespace App\Http\Controllers;

use App\Http\Resources\Storefront\ListingResource;
use App\Models\Agent;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Review;
use App\Models\Service;
use App\Services\AboutPageContent;
use App\Services\PlatformSettings;
use App\Services\ReviewEligibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function about(): Response
    {
        return Inertia::render('Storefront/PublicPage', [
            'page' => 'about',
            'aboutContent' => AboutPageContent::get(),
        ]);
    }

    public function contact(): Response
    {
        return Inertia::render('Storefront/Contact');
    }

    public function submitContact(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        ContactMessage::create($data + ['status' => 'new', 'ip_address' => $request->ip()]);

        return back()->with('success', 'Thanks for reaching out. Our team will reply within one business day.');
    }

    public function becomeAgent(): Response
    {
        return Inertia::render('Storefront/PublicPage', [
            'page' => 'become-agent',
            'agentBillingMode' => PlatformSettings::listingBillingMode(),
            'agentServices' => Service::query()
                ->where('status', 'active')
                ->with(['packages' => fn ($query) => $query->where('status', 'active')->orderBy('price')])
                ->orderBy('name')
                ->get()
                ->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'packages' => $service->packages->map(fn ($package) => [
                        'id' => $package->id,
                        'name' => $package->name,
                        'description' => $package->description,
                        'price' => (float) $package->price,
                        'duration_days' => $package->duration_days,
                        'listing_limit' => $package->listing_limit,
                    ])->values(),
                ])->values(),
        ]);
    }

    public function blog(Request $request): Response
    {
        $posts = BlogPost::published()->latest('published_at')->paginate(9)->withQueryString()
            ->through(fn (BlogPost $post) => $this->blogCard($post));

        return Inertia::render('Storefront/Blog', ['posts' => $posts]);
    }

    public function blogPost(string $slug): Response
    {
        $post = BlogPost::published()->where('slug', $slug)->firstOrFail();
        $related = BlogPost::published()
            ->where('id', '!=', $post->id)
            ->where('category', $post->category)
            ->latest('published_at')->take(3)->get()
            ->map(fn (BlogPost $item) => $this->blogCard($item));

        return Inertia::render('Storefront/BlogPost', [
            'post' => $this->blogCard($post) + ['paragraphs' => $post->paragraphs()],
            'related' => $related,
        ]);
    }

    public function vendors(Request $request): Response
    {
        $vendors = Agent::query()->where('status', 'active')
            ->withCount(['listings as product_count' => fn ($query) => $query->published()])
            ->when($request->string('search')->trim()->value(), fn ($query, $search) => $query->where('business_name', 'like', "%{$search}%"))
            ->orderByDesc('product_count')->orderBy('business_name')
            ->paginate(16)->withQueryString()
            ->through(fn (Agent $agent) => [
                'id' => (string) $agent->id,
                'slug' => $agent->slug,
                'name' => $agent->business_name,
                'description' => $agent->about ?: $agent->description,
                'location' => $agent->state ?: 'Nigeria',
                'product_count' => $agent->product_count,
                'verified' => $agent->verification_status === 'verified',
            ]);

        return Inertia::render('Storefront/Vendors', [
            'vendors' => $vendors,
            'search' => $request->input('search', ''),
        ]);
    }

    public function trackOrder(): Response
    {
        return Inertia::render('Storefront/TrackOrder', ['trackedOrder' => session('trackedOrder')]);
    }

    public function lookupOrder(Request $request)
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:190'],
        ]);
        $order = Order::query()
            ->where('order_number', strtoupper(trim($data['order_number'])))
            ->whereHas('customer', fn ($query) => $query->where('email', strtolower($data['email'])))
            ->with(['statusHistory' => fn ($query) => $query->select('id', 'order_id', 'status', 'created_at')->orderBy('created_at')])
            ->first();

        if (! $order) {
            throw ValidationException::withMessages([
                'order_number' => ['We could not find an order matching those details. Check the order number and email, then try again.'],
            ]);
        }

        return redirect()->route('storefront.track-order')->with('trackedOrder', [
            'order_number' => $order->order_number,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'placed_at' => optional($order->placed_at)->toIso8601String(),
            'history' => $order->statusHistory->map(fn ($entry) => [
                'status' => $entry->status,
                'created_at' => optional($entry->created_at)->toIso8601String(),
            ])->values(),
        ]);
    }

    private function blogCard(BlogPost $post): array
    {
        return [
            'id' => (string) $post->id,
            'slug' => $post->slug,
            'title' => $post->title,
            'category' => $post->category,
            'author' => $post->author,
            'excerpt' => $post->excerpt ?: Str::limit(strip_tags((string) $post->body), 160),
            'image' => $post->cover_image ?: 'https://picsum.photos/seed/blog-'.$post->id.'/800/500',
            'published_at' => optional($post->published_at)->toIso8601String(),
            'read_minutes' => $post->read_minutes,
        ];
    }

    public function deals(Request $request): Response
    {
        $request->merge(['on_sale' => true]);

        return $this->index($request);
    }

    public function services(Request $request): Response
    {
        return $this->index($request);
    }

    public function home(): Response
    {
        $listings = fn ($query, int $limit) => $query->take($limit)->get()
            ->map(fn (Listing $listing) => (new ListingResource($listing))->resolve())
            ->values();
        $listingBase = fn () => $this->listingQuery();
        $vendors = Agent::query()
            ->where('status', 'active')
            ->where('verification_status', 'verified')
            ->withCount(['listings as product_count' => fn ($query) => $query->published()])
            ->orderByDesc('product_count')
            ->take(12)
            ->get();

        return Inertia::render('Storefront/Home', [
            'hero' => $listings($listingBase()->latest('published_at'), 6),
            'hotDeals' => $listings($listingBase()->whereNotNull('sale_price')->orderByDesc('sales_count'), 10),
            'bestSellers' => $listings($listingBase()->orderByDesc('sales_count'), 20),
            'departments' => Category::query()->whereNull('parent_id')->orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'icon']),
            'departmentListings' => [
                'new_arrivals' => $listings($listingBase()->latest('published_at'), 12),
                'flash_deals' => $listings($listingBase()->whereNotNull('sale_price')->latest('published_at'), 12),
                'todays_deals' => $listings($listingBase()->whereNotNull('sale_price')->orderByDesc('sales_count'), 12),
                'featured' => $listings($listingBase()->where('featured', true)->latest('published_at'), 12),
            ],
            'vendors' => $vendors->map(fn (Agent $agent) => [
                'id' => (string) $agent->id,
                'slug' => $agent->slug,
                'name' => $agent->business_name,
                'logo' => $agent->logo ? Storage::disk('public')->url($agent->logo) : null,
                'location' => $agent->state ?: 'Nigeria',
                'verified' => $agent->verification_status === 'verified',
                'product_count' => $agent->product_count,
                'listings' => $listings($listingBase()->where('agent_id', $agent->id)->latest('published_at'), 8),
            ])->values(),
            'services' => Service::query()->where('status', 'active')->orderBy('name')->get()
                ->map(fn (Service $service) => [
                    'id' => (string) $service->id,
                    'name' => $service->name,
                    'slug' => $service->slug,
                    'icon' => $service->icon ?: 'Wrench',
                    'tagline' => $service->tagline ?: Str::limit(strip_tags((string) $service->description), 90),
                    'listings_count' => Listing::published()->where('service_id', $service->id)->count(),
                ])->values(),
            'serviceListings' => $listings($listingBase()->where('type', 'service')->latest('published_at'), 8),
            'sponsors' => [
                ['name' => 'Paystack', 'description' => 'Payments partner'],
                ['name' => 'Flutterwave', 'description' => 'Payments partner'],
                ['name' => 'GIG Logistics', 'description' => 'Delivery partner'],
                ['name' => 'Remita', 'description' => 'Payments partner'],
                ['name' => 'Kobo360', 'description' => 'Freight partner'],
                ['name' => 'Sterling Bank', 'description' => 'Financing partner'],
            ],
            'blogs' => BlogPost::published()->latest('published_at')->take(4)->get()
                ->map(fn (BlogPost $post) => [
                    'id' => (string) $post->id,
                    'slug' => $post->slug,
                    'title' => $post->title,
                    'category' => $post->category,
                    'excerpt' => $post->excerpt ?: Str::limit(strip_tags((string) $post->body), 150),
                    'image' => $post->cover_image ?: 'https://picsum.photos/seed/blog-'.$post->id.'/800/500',
                    'published_at' => optional($post->published_at)->toIso8601String(),
                    'read_minutes' => $post->read_minutes,
                ])->values(),
        ]);
    }

    public function index(Request $request): Response
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'service' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'in:product,service'],
            'brand' => ['nullable', 'string', 'max:120'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'rating' => ['nullable', 'in:3,4,4.5'],
            'in_stock' => ['nullable', 'boolean'],
            'on_sale' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:relevance,price_low,price_high,popular,rating'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $listings = $this->listingQuery()
            ->when($request->routeIs('storefront.services'), fn ($query) => $query->whereNotNull('service_id'))
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($data['category'] ?? null, fn ($query, $category) => $query->whereHas('category', fn ($query) => $query
                ->where('slug', $category)->orWhereHas('parent', fn ($query) => $query->where('slug', $category))))
            ->when($data['vendor'] ?? null, fn ($query, $vendor) => $query->whereHas('agent', fn ($query) => $query->where('slug', $vendor)))
            ->when($data['service'] ?? null, fn ($query, $service) => $query->whereHas('service', fn ($query) => $query->where('slug', $service)))
            ->when($data['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($data['brand'] ?? null, fn ($query, $brand) => $query->where('brand', $brand))
            ->when(isset($data['max_price']), fn ($query) => $query->whereRaw('COALESCE(sale_price, price) <= ?', [$data['max_price']]))
            ->when($data['rating'] ?? null, fn ($query, $rating) => $query->whereRaw(
                '(SELECT AVG(reviews.rating) FROM reviews WHERE reviews.subject_id = listings.id AND reviews.subject_type = ?) >= ?',
                ['listing', $rating],
            ))
            ->when($request->boolean('in_stock'), fn ($query) => $query->where(fn ($query) => $query
                ->where('type', 'service')->orWhere('quantity', '>', 0)))
            ->when($request->boolean('on_sale'), fn ($query) => $query->whereNotNull('sale_price')
                ->where(fn ($query) => $query->whereNull('deal_ends_at')->orWhere('deal_ends_at', '>', now())))
            ->when(($data['sort'] ?? 'relevance') === 'price_low', fn ($query) => $query->orderByRaw('COALESCE(sale_price, price) ASC'))
            ->when(($data['sort'] ?? 'relevance') === 'price_high', fn ($query) => $query->orderByRaw('COALESCE(sale_price, price) DESC'))
            ->when(($data['sort'] ?? 'relevance') === 'popular', fn ($query) => $query->orderByDesc('sales_count'))
            ->when(($data['sort'] ?? 'relevance') === 'rating', fn ($query) => $query->orderByDesc('reviews_avg_rating'))
            ->when(! in_array($data['sort'] ?? 'relevance', ['price_low', 'price_high', 'popular', 'rating'], true), fn ($query) => $query->latest('published_at'))
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Listing $listing) => (new ListingResource($listing))->resolve());

        return Inertia::render('Storefront/Shop', [
            'listings' => $listings,
            'pageMode' => $request->routeIs('storefront.services') ? 'services' : ($request->routeIs('storefront.deals') ? 'deals' : 'shop'),
            'serviceCategories' => $request->routeIs('storefront.services')
                ? Service::query()->where('status', 'active')->orderBy('name')->get()
                    ->map(fn (Service $service) => [
                        'id' => (string) $service->id,
                        'name' => $service->name,
                        'slug' => $service->slug,
                        'icon' => $service->icon ?: 'Wrench',
                        'description' => $service->tagline ?: Str::limit(strip_tags((string) $service->description), 90),
                        'listings_count' => Listing::published()->where('service_id', $service->id)->count(),
                    ])->values()
                : [],
            'vendors' => Agent::query()->where('status', 'active')
                ->whereHas('listings', fn ($query) => $query->published()
                    ->when($request->routeIs('storefront.services'), fn ($query) => $query->whereNotNull('service_id')))
                ->orderBy('business_name')
                ->get(['id', 'slug', 'business_name'])
                ->map(fn (Agent $agent) => [
                    'id' => (string) $agent->id,
                    'slug' => $agent->slug,
                    'name' => $agent->business_name,
                ])->values(),
            'brands' => Listing::query()->published()->whereNotNull('brand')->where('brand', '!=', '')
                ->distinct()->orderBy('brand')->pluck('brand'),
            'maxPrice' => (int) ceil((float) (Listing::query()->published()->selectRaw('MAX(COALESCE(sale_price, price)) as max_price')->value('max_price') ?: 500000)),
            'filters' => [
                'search' => $data['search'] ?? '',
                'category' => $data['category'] ?? '',
                'vendor' => $data['vendor'] ?? '',
                'service' => $data['service'] ?? '',
                'type' => $data['type'] ?? '',
                'brand' => $data['brand'] ?? '',
                'max_price' => $data['max_price'] ?? '',
                'rating' => $data['rating'] ?? '',
                'in_stock' => $request->boolean('in_stock'),
                'sort' => $data['sort'] ?? 'relevance',
            ],
        ]);
    }

    public function hydrateListings(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'string', 'max:1000'],
        ]);
        $ids = collect(explode(',', $data['ids']))
            ->filter(fn ($id) => ctype_digit($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->take(20)
            ->values();

        return response()->json($this->listingQuery()->whereIn('id', $ids)->get()
            ->map(fn (Listing $listing) => (new ListingResource($listing))->resolve())
            ->values());
    }

    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'source' => ['nullable', 'string', 'max:40'],
        ]);
        \App\Models\NewsletterSubscriber::updateOrCreate(
            ['email' => strtolower($data['email'])],
            [
                'source' => $data['source'] ?? 'storefront',
                'status' => 'subscribed',
                'confirmed_at' => now(),
            ],
        );

        return response()->json(['message' => 'You are subscribed — watch your inbox for deals.']);
    }

    public function show(Request $request, string $slug, ReviewEligibilityService $reviewEligibility): Response
    {
        $listing = $this->listingQuery()->where('slug', $slug)->firstOrFail();
        $related = $this->listingQuery()
            ->whereKeyNot($listing->id)
            ->where(fn ($query) => $query
                ->where('category_id', $listing->category_id)
                ->orWhere('agent_id', $listing->agent_id))
            ->take(4)
            ->get()
            ->map(fn (Listing $item) => (new ListingResource($item))->resolve());
        $reviews = Review::query()
            ->where('subject_type', 'listing')
            ->where('subject_id', $listing->id)
            ->where('status', 'published')
            ->with('customer:id,first_name,last_name')
            ->latest()
            ->take(30)
            ->get()
            ->map(fn (Review $review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'title' => $review->title,
                'body' => $review->body,
                'verified_purchase' => $review->verified_purchase,
                'created_at' => optional($review->created_at)->toIso8601String(),
                'customer_name' => trim(($review->customer?->first_name ?? '').' '.($review->customer?->last_name ?? '')) ?: 'Marketplace customer',
            ])->values();
        $customerReview = $request->user()
            ? Review::query()->where('customer_id', $request->user()->id)
                ->where('subject_type', 'listing')->where('subject_id', $listing->id)->exists()
            : false;
        $agent = $listing->agent;

        return Inertia::render('Storefront/Listing', [
            'listing' => (new ListingResource($listing))->resolve(),
            'related' => $related,
            'vendor' => [
                'id' => (string) $agent->id,
                'slug' => $agent->slug,
                'name' => $agent->business_name,
                'about' => $agent->about ?: $agent->description,
                'location' => collect([$agent->state, $agent->country])->filter()->implode(', ') ?: 'Nigeria',
                'verified' => $agent->verification_status === 'verified',
                'status' => $agent->status,
                'followers' => (int) $agent->followers,
                'listings_count' => $agent->listings()->published()->count(),
                'rating' => round((float) $agent->reviews()->where('status', 'published')->avg('rating'), 1),
                'reviews_count' => $agent->reviews()->where('status', 'published')->count(),
            ],
            'reviews' => $reviews,
            'reviewEligibility' => [
                'authenticated' => (bool) $request->user(),
                'canReview' => $request->user()
                    ? $reviewEligibility->hasPurchased($request->user(), 'listing', $listing->id)
                    : false,
                'alreadyReviewed' => $customerReview,
            ],
        ]);
    }

    public function submitListingReview(Request $request, string $slug, ReviewEligibilityService $eligibility)
    {
        $listing = Listing::published()->where('slug', $slug)->firstOrFail();
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
        ]);
        $customer = $request->user();

        if (! $eligibility->hasPurchased($customer, 'listing', $listing->id)) {
            throw ValidationException::withMessages([
                'review' => ['Reviews are available to customers after their order has been delivered.'],
            ]);
        }

        if (Review::query()->where('customer_id', $customer->id)
            ->where('subject_type', 'listing')->where('subject_id', $listing->id)->exists()) {
            throw ValidationException::withMessages([
                'review' => ['You have already reviewed this listing.'],
            ]);
        }

        Review::create([
            'customer_id' => $customer->id,
            'subject_type' => 'listing',
            'subject_id' => $listing->id,
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'verified_purchase' => true,
            'status' => 'published',
        ]);

        return back()->with('success', 'Thanks for sharing your verified review.');
    }

    private function listingQuery()
    {
        return Listing::query()
            ->published()
            ->with([
                'agent:id,slug,business_name,state,country,about,description,followers,verification_status,status',
                'category:id,name,slug,parent_id',
                'category.parent:id,slug',
                'service:id,name,slug',
                'images',
            ])
            ->withCount(['reviews' => fn ($query) => $query->where('status', 'published')])
            ->withAvg(['reviews as reviews_avg_rating' => fn ($query) => $query->where('status', 'published')], 'rating');
    }
}
