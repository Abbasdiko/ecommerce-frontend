<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\InventoryAdjustment;
use App\Models\Listing;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    use ApiResponse;

    private const LOW_STOCK = 5;

    private function agentId(Request $request): int
    {
        abort_unless($request->user()->agent, 403, 'Complete your business profile first.');

        return $request->user()->agent->id;
    }

    public function index(Request $request)
    {
        $agentId = $this->agentId($request);

        $listings = Listing::where('agent_id', $agentId)
            ->when($request->boolean('low_stock'), fn ($q) => $q->where('quantity', '<=', self::LOW_STOCK))
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%$s%"))
            ->orderBy('quantity')
            ->paginate($this->perPage($request), ['id', 'name', 'sku', 'quantity', 'stock_status', 'price', 'approval_status']);

        return $this->ok([
            'low_stock_threshold' => self::LOW_STOCK,
            'listings' => $listings,
        ]);
    }

    public function adjust(Request $request, Listing $listing)
    {
        abort_unless($listing->agent_id === $this->agentId($request), 403);

        $data = $request->validate([
            'change' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', Rule::in(['restock', 'correction', 'damage', 'return'])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $adjustment = DB::transaction(function () use ($listing, $data, $request) {
            $locked = Listing::whereKey($listing->id)->lockForUpdate()->first();
            $newQty = $locked->quantity + $data['change'];

            if ($newQty < 0) {
                abort(422, 'Adjustment would make stock negative.');
            }

            $locked->update([
                'quantity' => $newQty,
                'stock_status' => $newQty > 0 ? 'in_stock' : 'out_of_stock',
            ]);

            return InventoryAdjustment::create([
                'listing_id' => $locked->id,
                'agent_id' => $locked->agent_id,
                'user_id' => $request->user()->id,
                'change' => $data['change'],
                'quantity_after' => $newQty,
                'reason' => $data['reason'],
                'note' => $data['note'] ?? null,
            ]);
        });

        AuditLogger::record('inventory.adjusted', $listing, [], $data);

        return $this->created($adjustment, 'Stock updated');
    }

    public function history(Request $request, Listing $listing)
    {
        abort_unless($listing->agent_id === $this->agentId($request), 403);

        return $this->ok(
            $listing->inventoryAdjustments()->latest()->paginate($this->perPage($request))
        );
    }
}
