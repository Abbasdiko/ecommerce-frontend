<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Listing;
use App\Models\Review;
use App\Services\AuditLogger;
use App\Services\ReviewEligibilityService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    use ApiResponse;

    public function __construct(private ReviewEligibilityService $eligibility)
    {
    }

    public function index(Request $request)
    {
        $reviews = Review::where('customer_id', $request->user()->id)
            ->latest()
            ->paginate($this->perPage($request));

        $reviews->getCollection()->transform(fn (Review $r) => $this->decorate($r));

        return $this->ok($reviews);
    }

    /** Purchases the customer is still allowed to review. */
    public function pending(Request $request)
    {
        return $this->ok($this->eligibility->pending($request->user()));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject_type' => ['required', Rule::in(['listing', 'agent'])],
            'subject_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:2000'],
            'order_item_id' => ['nullable', 'integer'],
        ]);

        $customer = $request->user();

        if (! $this->eligibility->hasPurchased($customer, $data['subject_type'], $data['subject_id'])) {
            return $this->fail('You can only review items from your delivered orders.', null, 403);
        }

        // resolve subject existence
        $subject = $data['subject_type'] === 'listing'
            ? Listing::find($data['subject_id'])
            : Agent::find($data['subject_id']);
        abort_unless($subject, 422, 'The item you are reviewing no longer exists.');

        $exists = Review::where('customer_id', $customer->id)
            ->where('subject_type', $data['subject_type'])
            ->where('subject_id', $data['subject_id'])
            ->when($data['subject_type'] === 'agent', fn ($q) => $q) // one agent review per customer
            ->when($data['subject_type'] === 'listing' && empty($data['order_item_id']), fn ($q) => $q)
            ->when(! empty($data['order_item_id']), fn ($q) => $q->where('order_item_id', $data['order_item_id']))
            ->exists();

        if ($exists) {
            return $this->fail('You have already reviewed this.', null, 409);
        }

        $review = Review::create([
            'customer_id' => $customer->id,
            'subject_type' => $data['subject_type'],
            'subject_id' => $data['subject_id'],
            'order_item_id' => $data['order_item_id'] ?? null,
            'order_id' => isset($data['order_item_id'])
                ? optional(\App\Models\OrderItem::find($data['order_item_id']))->order_id
                : null,
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'verified_purchase' => true,
            'status' => 'published',
        ]);

        if (! empty($data['order_item_id'])) {
            \App\Models\OrderItem::whereKey($data['order_item_id'])
                ->where('agent_id', '>=', 0)
                ->update(['reviewed' => 1]);
        }

        AuditLogger::record('review.created', $review);

        return $this->created($this->decorate($review), 'Review submitted');
    }

    public function update(Request $request, Review $review)
    {
        abort_unless($review->customer_id === $request->user()->id, 403);

        $data = $request->validate([
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $review->update($data);

        return $this->ok($this->decorate($review->fresh()), 'Review updated');
    }

    public function destroy(Request $request, Review $review)
    {
        abort_unless($review->customer_id === $request->user()->id, 403);

        if ($review->order_item_id) {
            \App\Models\OrderItem::whereKey($review->order_item_id)->update(['reviewed' => 0]);
        }
        $review->delete();

        return $this->ok(null, 'Review deleted');
    }

    private function decorate(Review $review): array
    {
        $name = null;
        if ($review->subject_type === 'listing') {
            $name = optional(Listing::find($review->subject_id))->name;
        } elseif ($review->subject_type === 'agent') {
            $name = optional(Agent::find($review->subject_id))->business_name;
        }

        return array_merge($review->toArray(), ['subject_name' => $name]);
    }
}
