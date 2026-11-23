<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentSubscription;
use App\Models\Commission;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use ApiResponse;

    private function range(Request $request): array
    {
        $days = (int) $request->input('days', 30);
        $from = Carbon::today()->subDays($days - 1);

        return [$days, $from];
    }

    public function sales(Request $request)
    {
        $this->authorizePermission($request, 'report.view');
        [$days, $from] = $this->range($request);

        $paidOrders = Order::where('payment_status', 'successful')->where('placed_at', '>=', $from);

        $daily = (clone $paidOrders)->get(['placed_at', 'total'])
            ->groupBy(fn ($o) => optional($o->placed_at)->toDateString())
            ->map(fn ($g) => (float) $g->sum('total'));
        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $series[] = ['date' => $d, 'amount' => (float) ($daily[$d] ?? 0)];
        }

        $byAgent = OrderItem::whereHas('order', fn ($q) => $q->where('payment_status', 'successful')->where('placed_at', '>=', $from))
            ->selectRaw('agent_name, SUM(subtotal) as revenue, SUM(quantity) as units')
            ->groupBy('agent_name')->orderByDesc('revenue')->limit(10)->get();

        $byService = OrderItem::whereHas('order', fn ($q) => $q->where('payment_status', 'successful')->where('placed_at', '>=', $from))
            ->join('listings', 'order_items.listing_id', '=', 'listings.id')
            ->join('services', 'listings.service_id', '=', 'services.id')
            ->selectRaw('services.name as service, SUM(order_items.subtotal) as revenue')
            ->groupBy('services.name')->orderByDesc('revenue')->get();

        return $this->ok([
            'range_days' => $days,
            'totals' => [
                'gross_sales' => (float) (clone $paidOrders)->sum('total'),
                'orders' => (clone $paidOrders)->count(),
                'items_sold' => (int) OrderItem::whereHas('order', fn ($q) => $q->where('payment_status', 'successful')->where('placed_at', '>=', $from))->sum('quantity'),
                'avg_order_value' => round((float) (clone $paidOrders)->avg('total'), 2),
            ],
            'daily' => $series,
            'by_agent' => $byAgent,
            'by_service' => $byService,
        ]);
    }

    public function payments(Request $request)
    {
        $this->authorizePermission($request, 'report.view');
        [$days, $from] = $this->range($request);

        $orders = Order::where('placed_at', '>=', $from);

        $byMethod = (clone $orders)->where('payment_status', 'successful')
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total) as amount')
            ->groupBy('payment_method')->get();

        $gatewayPayments = Payment::where('created_at', '>=', $from)
            ->selectRaw('gateway, status, COUNT(*) as count, SUM(amount) as amount')
            ->groupBy('gateway', 'status')->get();

        return $this->ok([
            'range_days' => $days,
            'order_payments_by_method' => $byMethod,
            'gateway_transactions' => $gatewayPayments,
            'status_breakdown' => (clone $orders)->selectRaw('payment_status, COUNT(*) as c')->groupBy('payment_status')->pluck('c', 'payment_status'),
            'pending_bank_transfers' => Payment::where('gateway', 'bank_transfer')->where('status', 'awaiting_verification')->count(),
        ]);
    }

    public function commissions(Request $request)
    {
        $this->authorizePermission($request, 'report.view');
        [$days, $from] = $this->range($request);

        $base = Commission::whereHas('order', fn ($q) => $q->where('placed_at', '>=', $from));

        return $this->ok([
            'range_days' => $days,
            'totals' => [
                'total' => (float) (clone $base)->whereIn('status', ['pending', 'finalized'])->sum('commission_amount'),
                'finalized' => (float) (clone $base)->where('status', 'finalized')->sum('commission_amount'),
                'reversed' => (float) (clone $base)->where('status', 'reversed')->sum('commission_amount'),
            ],
            'by_agent' => (clone $base)->join('agents', 'commissions.agent_id', '=', 'agents.id')
                ->selectRaw('agents.business_name, SUM(commissions.commission_amount) as amount')
                ->groupBy('agents.business_name')->orderByDesc('amount')->limit(10)->get(),
        ]);
    }

    public function agents(Request $request)
    {
        $this->authorizePermission($request, 'report.view');

        return $this->ok([
            'counts' => [
                'total' => Agent::count(),
                'active' => Agent::where('status', 'active')->count(),
                'pending' => Agent::where('status', 'pending')->count(),
                'suspended' => Agent::where('status', 'suspended')->count(),
                'with_active_subscription' => AgentSubscription::where('status', 'active')->where('end_date', '>', now())->distinct('agent_id')->count('agent_id'),
            ],
            'subscription_revenue' => (float) AgentSubscription::where('payment_status', 'successful')->sum('amount'),
            'top_agents' => OrderItem::selectRaw('agent_name, SUM(subtotal) as revenue')
                ->groupBy('agent_name')->orderByDesc('revenue')->limit(10)->get(),
            'expiring_soon' => AgentSubscription::where('status', 'active')
                ->whereBetween('end_date', [now(), now()->addDays(7)])
                ->with('agent:id,business_name')->get(['id', 'agent_id', 'end_date']),
        ]);
    }

    public function coupons(Request $request)
    {
        $this->authorizePermission($request, 'report.view');

        return $this->ok([
            'totals' => [
                'redemptions' => CouponUsage::count(),
                'total_discount' => (float) CouponUsage::sum('discount_amount'),
            ],
            'most_used' => CouponUsage::join('coupons', 'coupon_usages.coupon_id', '=', 'coupons.id')
                ->selectRaw('coupons.code, coupons.name, COUNT(*) as uses, SUM(coupon_usages.discount_amount) as discount')
                ->groupBy('coupons.code', 'coupons.name')->orderByDesc('uses')->limit(10)->get(),
        ]);
    }
}
