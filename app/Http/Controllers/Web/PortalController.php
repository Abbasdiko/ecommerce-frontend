<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AgentOrder;
use App\Models\Order;
use App\Services\DashboardStatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    public function dashboard(Request $request, DashboardStatsService $stats): Response
    {
        return Inertia::render('Portal/Dashboard', [
            'summary' => $stats->forUser($request->user()),
        ]);
    }

    public function orders(Request $request): Response
    {
        $user = $request->user();
        $pageSize = min(50, max(1, (int) $request->query('per_page', 20)));

        if ($user->hasRole('customer')) {
            $orders = $user->orders()->withCount('items')->latest('placed_at')->paginate($pageSize)->withQueryString();
            $rows = $orders->through(fn (Order $order) => [
                'reference' => $order->order_number,
                'placed_at' => $order->placed_at,
                'total' => (float) $order->total,
                'payment_status' => $order->payment_status,
                'status' => $order->order_status,
                'items_count' => $order->items_count,
            ]);
        } elseif ($user->hasRole('agent')) {
            $agent = $user->agent;
            abort_unless($agent, 403, 'Agent onboarding is required.');
            $orders = AgentOrder::where('agent_id', $agent->id)
                ->with('order:id,order_number,placed_at,payment_status')
                ->latest()
                ->paginate($pageSize)
                ->withQueryString();
            $rows = $orders->through(fn (AgentOrder $agentOrder) => [
                'reference' => $agentOrder->order?->order_number ?? $agentOrder->reference,
                'placed_at' => $agentOrder->order?->placed_at,
                'total' => (float) $agentOrder->total,
                'payment_status' => $agentOrder->order?->payment_status ?? 'pending',
                'status' => $agentOrder->status,
                'items_count' => null,
            ]);
        } else {
            abort_unless($user->hasRole('super-admin') || $user->can('order.view'), 403);
            $orders = Order::query()
                ->withCount('items')
                ->latest('placed_at')
                ->paginate($pageSize)
                ->withQueryString();
            $rows = $orders->through(fn (Order $order) => [
                'reference' => $order->order_number,
                'placed_at' => $order->placed_at,
                'total' => (float) $order->total,
                'payment_status' => $order->payment_status,
                'status' => $order->order_status,
                'items_count' => $order->items_count,
            ]);
        }

        return Inertia::render('Portal/Orders', [
            'orders' => $rows,
            'scope' => $user->hasRole('customer') ? 'customer' : ($user->hasRole('agent') ? 'agent' : 'platform'),
        ]);
    }
}
