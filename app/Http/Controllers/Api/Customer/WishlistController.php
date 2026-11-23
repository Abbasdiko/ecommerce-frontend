<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Wishlist;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $items = Wishlist::where('customer_id', $request->user()->id)
            ->with(['listing' => fn ($q) => $q->with('agent:id,business_name')->withCount('reviews')])
            ->latest()
            ->paginate($this->perPage($request));

        return $this->ok($items);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['listing_id' => ['required', 'exists:listings,id']]);

        $listing = Listing::findOrFail($data['listing_id']);

        $item = Wishlist::firstOrCreate([
            'customer_id' => $request->user()->id,
            'listing_id' => $listing->id,
        ]);

        return $this->created($item->load('listing:id,name,slug'), 'Added to wishlist');
    }

    public function destroy(Request $request, Listing $listing)
    {
        Wishlist::where('customer_id', $request->user()->id)
            ->where('listing_id', $listing->id)
            ->delete();

        return $this->ok(null, 'Removed from wishlist');
    }
}
