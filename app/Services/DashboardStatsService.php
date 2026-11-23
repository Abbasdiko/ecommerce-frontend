<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentSubscription;
use App\Models\Listing;
use App\Models\Order;
use App\Models\ReferralCommission;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Support\Carbon;

class DashboardStatsService
{
    public function forUser(User $user): array
    {
        if ($user->hasAnyRole(['super-admin', 'admin', 'finance', 'support'])) {
            return $this->platform($user);
        }

        if ($user->hasRole('agent')) {
            return $this->agent($user);
        }

        return $this->customer($user);
    }

    protected function platform(User $user): array
    {
        $todayRevenue = (float) Order::where('payment_status', 'successful')
            ->whereDate('placed_at', \Illuminate\Support\Carbon::today())->sum('total');

        return [
            'scope' => 'platform',
            'widgets' => [
                'total_customers' => User::role('customer')->count(),
                'total_agents' => Agent::count(),
                'active_agents' => Agent::where('status', 'active')->count(),
                'pending_agents' => Agent::where('status', 'pending')->count(),
                'active_subscriptions' => AgentSubscription::where('status', 'active')->where('end_date', '>', now())->count(),
                'total_listings' => Listing::count(),
                'pending_listings' => Listing::where('approval_status', 'pending')->count(),
                'todays_orders' => Order::whereDate('placed_at', \Illuminate\Support\Carbon::today())->count(),
                'todays_revenue' => $todayRevenue,
                'platform_commission' => (float) \App\Models\Commission::whereIn('status', ['pending', 'finalized'])->sum('commission_amount'),
                'pending_bank_transfers' => \App\Models\Payment::where('gateway', 'bank_transfer')->where('status', 'awaiting_verification')->count(),
                'pending_withdrawals' => \App\Models\Withdrawal::where('status', 'pending')->count(),
                'pending_refunds' => \App\Models\ReturnRequest::whereIn('status', ['requested', 'approved', 'processing'])->count(),
                'open_disputes' => \App\Models\Dispute::whereIn('status', ['open', 'under_review'])->count(),
                'open_tickets' => \App\Models\SupportTicket::whereIn('status', ['open', 'pending'])->count(),
            ],
            'charts' => [
                'new_agents_14d' => $this->dailySeries(Agent::query(), 14),
                'revenue_14d' => $this->dailySeriesRevenue(14),
            ],
            'queues' => [
                'agent_approvals' => Agent::where('status', 'pending')->latest()->take(5)
                    ->get(['id', 'uuid', 'business_name', 'created_at']),
                'listing_approvals' => Listing::where('approval_status', 'pending')->latest()->take(5)
                    ->with('agent:id,business_name')->get(['id', 'uuid', 'name', 'agent_id', 'created_at']),
            ],
        ];
    }

    protected function dailySeriesRevenue(int $days): array
    {
        $start = \Illuminate\Support\Carbon::today()->subDays($days - 1);
        $rows = Order::where('payment_status', 'successful')->where('placed_at', '>=', $start)
            ->get(['placed_at', 'total'])
            ->groupBy(fn ($o) => optional($o->placed_at)->toDateString())
            ->map(fn ($g) => (float) $g->sum('total'));
        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $start->copy()->addDays($i)->toDateString();
            $series[] = ['date' => $d, 'count' => (float) ($rows[$d] ?? 0)];
        }

        return $series;
    }

    protected function agent(User $user): array
    {
        $agent = $user->agent;

        if (! $agent) {
            return ['scope' => 'agent', 'onboarding_required' => true, 'widgets' => []];
        }

        $listings = $agent->listings();
        $agentOrders = \App\Models\AgentOrder::where('agent_id', $agent->id);
        $wallet = $agent->wallet();
        $today = \Illuminate\Support\Carbon::today();

        $soldStatuses = ['processing', 'ready_for_dispatch', 'dispatched', 'out_for_delivery', 'delivered'];

        return [
            'scope' => 'agent',
            'onboarding_required' => false,
            'kyc_required' => ! $agent->isKycVerified(),
            'agent' => [
                'business_name' => $agent->business_name,
                'status' => $agent->status,
                'verification_status' => $agent->verification_status,
            ],
            'widgets' => [
                'total_sales' => (float) (clone $agentOrders)->whereIn('status', $soldStatuses)->sum('subtotal'),
                'today_sales' => (float) (clone $agentOrders)->whereIn('status', $soldStatuses)
                    ->whereHas('order', fn ($q) => $q->whereDate('placed_at', $today))->sum('subtotal'),
                'pending_orders' => (clone $agentOrders)->whereIn('status', ['pending', 'processing'])->count(),
                'completed_orders' => (clone $agentOrders)->where('status', 'delivered')->count(),
                'active_listings' => (clone $listings)->where('approval_status', 'approved')->count(),
                'pending_listings' => (clone $listings)->where('approval_status', 'pending')->count(),
                'pending_earnings' => (float) $wallet->pending_balance,
                'available_balance' => (float) $wallet->available_balance,
                'total_commission' => (float) \App\Models\Commission::where('agent_id', $agent->id)
                    ->whereIn('status', ['pending', 'finalized'])->sum('commission_amount'),
                'active_subscriptions' => $agent->subscriptions()->where('status', 'active')->where('end_date', '>', now())->count(),
                'expired_subscriptions' => $agent->subscriptions()->where('status', 'expired')->count(),
                'coupon_usages' => \App\Models\CouponUsage::whereHas('coupon', fn ($q) => $q->where('owner_type', 'agent')->where('owner_id', $agent->id))->count(),
            ],
            'charts' => [
                'sales_14d' => $this->agentSalesSeries($agent->id, 14),
                'orders_by_status' => (clone $agentOrders)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
            ],
            'recent_orders' => (clone $agentOrders)->with('order:id,order_number,placed_at')
                ->latest()->take(6)->get(['id', 'reference', 'order_id', 'subtotal', 'status']),
            'top_products' => \App\Models\OrderItem::where('agent_id', $agent->id)
                ->selectRaw('product_name, SUM(quantity) as units, SUM(subtotal) as revenue')
                ->groupBy('product_name')->orderByDesc('revenue')->limit(5)->get(),
            'subscriptions' => $agent->subscriptions()->with(['service:id,name', 'package:id,name,listing_limit'])
                ->latest()->take(5)->get(),
        ];
    }

    protected function agentSalesSeries(int $agentId, int $days): array
    {
        $from = \Illuminate\Support\Carbon::today()->subDays($days - 1);

        $rows = \App\Models\AgentOrder::where('agent_id', $agentId)
            ->whereIn('status', ['processing', 'ready_for_dispatch', 'dispatched', 'out_for_delivery', 'delivered'])
            ->whereHas('order', fn ($q) => $q->where('placed_at', '>=', $from))
            ->with('order:id,placed_at')
            ->get()
            ->groupBy(fn ($ao) => optional($ao->order->placed_at)->toDateString())
            ->map(fn ($g) => (float) $g->sum('subtotal'));

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $series[] = ['date' => $d, 'amount' => (float) ($rows[$d] ?? 0)];
        }

        return $series;
    }

    protected function customer(User $user): array
    {
        $orders = Order::where('customer_id', $user->id);
        $wallet = $user->customerWallet();

        return [
            'scope' => 'customer',
            'widgets' => [
                'total_orders' => (clone $orders)->count(),
                'pending_orders' => (clone $orders)->whereNotIn('order_status', ['delivered', 'cancelled', 'returned'])->count(),
                'delivered_orders' => (clone $orders)->where('order_status', 'delivered')->count(),
                'total_spent' => (float) (clone $orders)->where('payment_status', 'successful')->sum('total'),
                'wishlist_items' => Wishlist::where('customer_id', $user->id)->count(),
                'wallet_available' => (float) $wallet->available_balance,
                'wallet_pending' => (float) $wallet->pending_balance,
                'referral_earnings' => (float) ReferralCommission::where('referrer_user_id', $user->id)
                    ->whereIn('status', ['available', 'paid'])->sum('commission_amount'),
            ],
            'charts' => [
                'spend_by_month' => $this->spendByMonth($user, 6),
                'orders_14d' => $this->dailySeries(Order::where('customer_id', $user->id), 14, 'placed_at'),
            ],
            'recent_orders' => (clone $orders)->latest('placed_at')->take(5)
                ->get(['id', 'order_number', 'total', 'order_status', 'placed_at']),
            'profile' => [
                'name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'referral_code' => $user->referral_code,
            ],
        ];
    }

    protected function spendByMonth(User $user, int $months): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);

        $rows = Order::where('customer_id', $user->id)
            ->where('payment_status', 'successful')
            ->where('placed_at', '>=', $start)
            ->get(['placed_at', 'total'])
            ->groupBy(fn ($o) => optional($o->placed_at)->format('Y-m'))
            ->map(fn ($g) => (float) $g->sum('total'));

        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $key = $start->copy()->addMonths($i)->format('Y-m');
            $series[] = ['month' => $key, 'amount' => (float) ($rows[$key] ?? 0)];
        }

        return $series;
    }

    protected function dailySeries($query, int $days, string $column = 'created_at'): array
    {
        $start = Carbon::today()->subDays($days - 1);

        $rows = $query->where($column, '>=', $start)
            ->get([$column])
            ->groupBy(fn ($row) => optional($row->{$column})->toDateString())
            ->map->count();

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $series[] = ['date' => $date, 'count' => (int) ($rows[$date] ?? 0)];
        }

        return $series;
    }
}
