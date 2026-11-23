<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Agent;
use App\Models\AgentOrder;
use App\Models\BankAccount;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Review;
use App\Models\ReferralCommission;
use App\Models\ReturnRequest;
use App\Models\Settlement;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\WalletService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CustomerPortalSeeder extends Seeder
{
    private const REFERRAL_RATE = 2.0; // percent of order subtotal

    public function run(): void
    {
        $wallets = app(WalletService::class);

        $customers = User::role('customer')->orderBy('id')->get();
        if ($customers->count() < 2) {
            return;
        }

        $referrer = $customers->first();
        $published = Listing::where('approval_status', 'approved')
            ->with('agent')
            ->get();

        if ($published->isEmpty()) {
            return;
        }

        // customers 2..n are referred by customer 1
        foreach ($customers->skip(1) as $c) {
            $c->update(['referred_by' => $referrer->id]);
        }

        foreach ($customers as $index => $customer) {
            $this->addresses($customer);
            $this->bankAccount($customer);

            $orderPlan = [
                ['delivered', 20],
                ['dispatched', 6],
                ['processing', 3],
                ['pending', 1],
            ];
            if ($index % 3 === 0) {
                $orderPlan[] = ['cancelled', 12];
            }

            foreach ($orderPlan as [$finalStatus, $daysAgo]) {
                $this->buildOrder($customer, $published, $finalStatus, $daysAgo, $wallets);
            }

            // wishlist
            foreach ($published->random(min(4, $published->count())) as $listing) {
                Wishlist::firstOrCreate(['customer_id' => $customer->id, 'listing_id' => $listing->id]);
            }
        }

        // A pending payout request for the referrer
        $wallet = $wallets->walletFor($referrer);
        if ((float) $wallet->available_balance > 1000) {
            $bank = $referrer->bankAccounts()->first();
            $amount = round((float) $wallet->available_balance / 2, 2);
            Settlement::create([
                'user_id' => $referrer->id,
                'wallet_id' => $wallet->id,
                'bank_account_id' => $bank?->id,
                'amount' => $amount,
                'fee' => 50,
                'net_amount' => $amount - 50,
                'status' => 'pending',
                'note' => 'Requested via customer portal (demo).',
            ]);
        }
    }

    private function addresses(User $customer): void
    {
        Address::firstOrCreate(
            ['user_id' => $customer->id, 'type' => 'shipping'],
            [
                'label' => 'Home', 'contact_name' => $customer->full_name,
                'contact_phone' => '0803' . random_int(1000000, 9999999),
                'line1' => random_int(1, 90) . ' Marina Road', 'city' => 'Lagos',
                'state' => 'Lagos', 'lga' => 'Lagos Island', 'country' => 'Nigeria', 'is_default' => true,
            ]
        );
        Address::firstOrCreate(
            ['user_id' => $customer->id, 'type' => 'billing'],
            [
                'label' => 'Billing', 'contact_name' => $customer->full_name,
                'contact_phone' => '0803' . random_int(1000000, 9999999),
                'line1' => random_int(1, 90) . ' Awolowo Way', 'city' => 'Ikeja',
                'state' => 'Lagos', 'country' => 'Nigeria', 'is_default' => true,
            ]
        );
    }

    private function bankAccount(User $customer): void
    {
        BankAccount::firstOrCreate(
            ['user_id' => $customer->id, 'account_number' => (string) random_int(1000000000, 9999999999)],
            [
                'bank_name' => collect(['GTBank', 'Access Bank', 'Zenith Bank', 'UBA'])->random(),
                'account_name' => strtoupper($customer->full_name),
                'is_default' => true,
            ]
        );
    }

    private function buildOrder(User $customer, $published, string $finalStatus, int $daysAgo, WalletService $wallets): void
    {
        $placedAt = Carbon::now()->subDays($daysAgo);
        $lines = $published->random(min(random_int(1, 3), $published->count()));

        $shipping = $customer->addresses()->where('type', 'shipping')->first();
        $billing = $customer->addresses()->where('type', 'billing')->first();

        $order = Order::create([
            'customer_id' => $customer->id,
            'subtotal' => 0, 'delivery_fee' => 1500, 'total' => 0,
            'payment_method' => collect(['paystack', 'flutterwave', 'pay_on_delivery'])->random(),
            'payment_status' => $finalStatus === 'pending' ? 'pending' : 'successful',
            'order_status' => 'pending',
            'billing_address_id' => $billing?->id,
            'shipping_address_id' => $shipping?->id,
            'placed_at' => $placedAt,
            'created_at' => $placedAt,
            'updated_at' => $placedAt,
        ]);

        $byAgent = $lines->groupBy('agent_id');
        $orderSubtotal = 0;
        $orderCommission = 0;

        foreach ($byAgent as $agentId => $agentLines) {
            /** @var Agent $agent */
            $agent = $agentLines->first()->agent;
            $rate = optional($agent->subscriptions()->where('status', 'active')->first()->package ?? null)->commission_rate ?? 10;

            $agentOrder = AgentOrder::create([
                'order_id' => $order->id,
                'agent_id' => $agentId,
                'status' => $this->agentStatusFor($finalStatus),
                'tracking_number' => in_array($finalStatus, ['dispatched', 'delivered'], true) ? 'TRK' . strtoupper(Str::random(9)) : null,
                'created_at' => $placedAt, 'updated_at' => $placedAt,
            ]);

            $aoSubtotal = 0;
            $aoCommission = 0;

            foreach ($agentLines as $listing) {
                $qty = random_int(1, 3);
                $price = (float) ($listing->sale_price ?? $listing->price);
                $sub = $price * $qty;
                $commission = round($sub * $rate / 100, 2);

                OrderItem::create([
                    'order_id' => $order->id,
                    'agent_order_id' => $agentOrder->id,
                    'agent_id' => $agentId,
                    'listing_id' => $listing->id,
                    'product_name' => $listing->name,
                    'sku' => $listing->sku,
                    'agent_name' => $agent->business_name,
                    'unit_price' => $price,
                    'quantity' => $qty,
                    'subtotal' => $sub,
                    'total' => $sub,
                    'commission' => $commission,
                    'agent_earning' => $sub - $commission,
                    'created_at' => $placedAt, 'updated_at' => $placedAt,
                ]);

                $aoSubtotal += $sub;
                $aoCommission += $commission;
            }

            $agentOrder->update([
                'subtotal' => $aoSubtotal,
                'commission' => $aoCommission,
                'total' => $aoSubtotal,
                'agent_earning' => $aoSubtotal - $aoCommission,
            ]);

            $orderSubtotal += $aoSubtotal;
            $orderCommission += $aoCommission;
        }

        $order->update([
            'subtotal' => $orderSubtotal,
            'total' => $orderSubtotal + $order->delivery_fee,
        ]);

        $this->replayHistory($order, $finalStatus, $placedAt);

        // Referral commission for the referrer when a referred customer buys
        if ($customer->referred_by && $finalStatus !== 'cancelled') {
            $base = $orderSubtotal;
            $amount = round($base * self::REFERRAL_RATE / 100, 2);
            $matured = $finalStatus === 'delivered';

            $rc = ReferralCommission::create([
                'referrer_user_id' => $customer->referred_by,
                'referred_user_id' => $customer->id,
                'order_id' => $order->id,
                'commission_base' => $base,
                'commission_rate' => self::REFERRAL_RATE,
                'commission_amount' => $amount,
                'status' => $matured ? 'available' : 'pending',
            ]);

            $referrer = User::find($customer->referred_by);
            $wallet = $wallets->walletFor($referrer);
            if ($matured) {
                $wallets->creditAvailable($wallet, $amount, 'referral_commission', "Referral commission — order {$order->order_number}", $rc);
            } else {
                $wallets->creditPending($wallet, $amount, 'referral_commission', "Pending referral commission — order {$order->order_number}", $rc);
            }
        }

        // Reviews + return request on delivered orders
        if ($finalStatus === 'delivered') {
            $items = $order->items()->get();
            foreach ($items as $i => $item) {
                if ($i === 0) {
                    // leave first item unreviewed to populate "pending reviews"
                    continue;
                }
                Review::firstOrCreate(
                    ['customer_id' => $customer->id, 'subject_type' => 'listing', 'subject_id' => $item->listing_id, 'order_item_id' => $item->id],
                    ['order_id' => $order->id, 'rating' => random_int(3, 5), 'body' => 'Solid product, delivered as described.', 'verified_purchase' => true, 'status' => 'published']
                );
                $item->update(['reviewed' => 1]);
            }

            if (random_int(1, 3) === 1 && $items->count()) {
                ReturnRequest::create([
                    'order_id' => $order->id,
                    'order_item_id' => $items->first()->id,
                    'customer_id' => $customer->id,
                    'type' => 'refund',
                    'reason' => 'Item did not match the listing photos.',
                    'amount_requested' => $items->first()->total,
                    'status' => 'requested',
                ]);
            }
        }
    }

    private function agentStatusFor(string $orderStatus): string
    {
        return match ($orderStatus) {
            'delivered' => 'delivered',
            'dispatched' => 'dispatched',
            'processing' => 'processing',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }

    private function replayHistory(Order $order, string $finalStatus, Carbon $placedAt): void
    {
        $sequence = match ($finalStatus) {
            'cancelled' => ['pending', 'cancelled'],
            'pending' => ['pending'],
            'processing' => ['pending', 'confirmed', 'processing'],
            'dispatched' => ['pending', 'confirmed', 'processing', 'ready_for_dispatch', 'dispatched'],
            'delivered' => ['pending', 'confirmed', 'processing', 'ready_for_dispatch', 'dispatched', 'out_for_delivery', 'delivered'],
            default => ['pending'],
        };

        foreach ($sequence as $step => $status) {
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $status,
                'note' => null,
                'created_at' => $placedAt->copy()->addHours($step * 8),
            ]);
        }

        $order->update(['order_status' => end($sequence)]);
    }
}
