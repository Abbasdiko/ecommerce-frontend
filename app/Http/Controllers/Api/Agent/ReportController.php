<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentOrder;
use App\Models\Commission;
use App\Models\OrderItem;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    use ApiResponse;

    public function sales(Request $request)
    {
        $agent = $request->user()->agent;
        abort_unless($agent, 403, 'Complete your business profile first.');

        $days = (int) $request->input('days', 30);
        $from = Carbon::today()->subDays($days - 1);

        $agentOrders = AgentOrder::where('agent_id', $agent->id)
            ->whereHas('order', fn ($q) => $q->where('placed_at', '>=', $from))
            ->with('order:id,placed_at')
            ->get();

        $paidStatuses = ['processing', 'ready_for_dispatch', 'dispatched', 'out_for_delivery', 'delivered'];
        $paid = $agentOrders->whereIn('status', $paidStatuses);

        // daily gross series
        $byDay = $paid->groupBy(fn ($ao) => optional($ao->order->placed_at)->toDateString())
            ->map(fn ($g) => (float) $g->sum('subtotal'));
        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $series[] = ['date' => $d, 'amount' => (float) ($byDay[$d] ?? 0)];
        }

        // top products
        $topProducts = OrderItem::where('agent_id', $agent->id)
            ->whereHas('order', fn ($q) => $q->where('placed_at', '>=', $from))
            ->selectRaw('product_name, SUM(quantity) as units, SUM(subtotal) as revenue')
            ->groupBy('product_name')
            ->orderByDesc('revenue')
            ->limit(8)
            ->get();

        return $this->ok([
            'range_days' => $days,
            'totals' => [
                'orders' => $agentOrders->count(),
                'gross_sales' => (float) $paid->sum('subtotal'),
                'commission' => (float) Commission::where('agent_id', $agent->id)
                    ->whereHas('order', fn ($q) => $q->where('placed_at', '>=', $from))->sum('commission_amount'),
                'net_earnings' => (float) $paid->sum('agent_earning'),
                'delivered' => $agentOrders->where('status', 'delivered')->count(),
                'cancelled' => $agentOrders->where('status', 'cancelled')->count(),
            ],
            'daily_sales' => $series,
            'status_breakdown' => $agentOrders->groupBy('status')->map->count(),
            'top_products' => $topProducts,
        ]);
    }
}
