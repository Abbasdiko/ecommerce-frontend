<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Review;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    use ApiResponse;

    private function agent(Request $request)
    {
        $agent = $request->user()->agent;
        abort_unless($agent, 403, 'Complete your business profile first.');

        return $agent;
    }

    public function index(Request $request)
    {
        $agent = $this->agent($request);
        $listingIds = Listing::where('agent_id', $agent->id)->pluck('id');

        $query = Review::query()
            ->with('customer:id,first_name,last_name')
            ->where(function ($q) use ($listingIds, $agent) {
                $q->where(fn ($q) => $q->where('subject_type', 'listing')->whereIn('subject_id', $listingIds))
                    ->orWhere(fn ($q) => $q->where('subject_type', 'agent')->where('subject_id', $agent->id));
            })
            ->when($request->filled('responded'), function ($q) use ($request) {
                $request->boolean('responded') ? $q->whereNotNull('response') : $q->whereNull('response');
            })
            ->latest();

        $reviews = $query->paginate($this->perPage($request));

        $names = Listing::whereIn('id', $listingIds)->pluck('name', 'id');
        $reviews->getCollection()->transform(function (Review $r) use ($names, $agent) {
            $r->subject_name = $r->subject_type === 'listing' ? ($names[$r->subject_id] ?? 'Product') : $agent->business_name;

            return $r;
        });

        $summary = [
            'total' => Review::where(function ($q) use ($listingIds, $agent) {
                $q->where(fn ($q) => $q->where('subject_type', 'listing')->whereIn('subject_id', $listingIds))
                    ->orWhere(fn ($q) => $q->where('subject_type', 'agent')->where('subject_id', $agent->id));
            })->count(),
            'average' => round((float) Review::where(function ($q) use ($listingIds, $agent) {
                $q->where(fn ($q) => $q->where('subject_type', 'listing')->whereIn('subject_id', $listingIds))
                    ->orWhere(fn ($q) => $q->where('subject_type', 'agent')->where('subject_id', $agent->id));
            })->avg('rating'), 2),
        ];

        return $this->ok(['summary' => $summary, 'reviews' => $reviews]);
    }

    public function respond(Request $request, Review $review)
    {
        $agent = $this->agent($request);

        $ownsSubject = ($review->subject_type === 'agent' && $review->subject_id === $agent->id)
            || ($review->subject_type === 'listing' && Listing::where('id', $review->subject_id)->where('agent_id', $agent->id)->exists());
        abort_unless($ownsSubject, 403);

        $data = $request->validate(['response' => ['required', 'string', 'max:1000']]);

        $review->update(['response' => $data['response'], 'responded_at' => now()]);
        AuditLogger::record('review.responded', $review);

        return $this->ok($review->fresh(), 'Response posted');
    }
}
