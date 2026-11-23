<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\AgentOrder;
use App\Models\BankAccount;
use App\Models\Commission;
use App\Models\Coupon;
use App\Models\Review;
use App\Models\Withdrawal;
use App\Services\WalletService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AgentPortalSeeder extends Seeder
{
    public function run(): void
    {
        $wallets = app(WalletService::class);

        // ---- Bank accounts + KYC documents for verified agents ----
        foreach (Agent::where('verification_status', 'verified')->with('user')->get() as $agent) {
            BankAccount::firstOrCreate(
                ['user_id' => $agent->user_id, 'account_number' => (string) random_int(1000000000, 9999999999)],
                [
                    'bank_name' => collect(['GTBank', 'Access Bank', 'Zenith Bank', 'UBA'])->random(),
                    'account_name' => strtoupper($agent->business_name),
                    'is_default' => true,
                ]
            );
        }

        foreach (Agent::where('verification_status', 'verified')->get() as $agent) {
            foreach (['cac', 'id_card', 'proof_of_address'] as $type) {
                $agent->documents()->firstOrCreate(
                    ['type' => $type],
                    ['file_path' => "kyc/{$agent->id}/{$type}-sample.pdf", 'status' => 'approved'],
                );
            }
            $agent->update(['kyc_submitted_at' => now()->subDays(20)]);
        }

        // ---- Commissions + agent wallet from existing sub-orders ----
        $soldStatuses = ['processing', 'ready_for_dispatch', 'dispatched', 'out_for_delivery', 'delivered'];

        AgentOrder::with(['items', 'agent.user', 'order'])->get()->each(function (AgentOrder $ao) use ($wallets, $soldStatuses) {
            if (! in_array($ao->status, $soldStatuses, true)) {
                return;
            }

            foreach ($ao->items as $item) {
                Commission::firstOrCreate(
                    ['agent_order_id' => $ao->id, 'order_item_id' => $item->id],
                    [
                        'order_id' => $ao->order_id,
                        'agent_id' => $ao->agent_id,
                        'commission_base' => $item->subtotal,
                        'commission_rate' => $item->subtotal > 0 ? round($item->commission / $item->subtotal * 100, 3) : 0,
                        'commission_amount' => $item->commission,
                        'agent_earning' => $item->agent_earning,
                        'status' => $ao->status === 'delivered' ? 'finalized' : 'pending',
                    ]
                );
            }

            if ($ao->status === 'delivered' && $ao->agent && (float) $ao->agent_earning > 0) {
                $deliveredAt = ($ao->order->placed_at ?? now())->copy()->addDays(6);
                $ao->update(['delivered_at' => $deliveredAt]);

                $wallet = $wallets->walletFor($ao->agent->user, 'agent');
                $already = $wallet->transactions()
                    ->where('reference_type', 'AgentOrder')->where('reference_id', $ao->id)->exists();

                if (! $already) {
                    // Older deliveries: released to available. Recent: still pending.
                    if ($deliveredAt->lt(now()->subDays(3))) {
                        $wallets->creditAvailable($wallet, (float) $ao->agent_earning, 'sale', "Sale earnings — {$ao->reference}", $ao);
                        $ao->update(['funds_released_at' => now()]);
                    } else {
                        $wallets->creditPending($wallet, (float) $ao->agent_earning, 'sale', "Pending sale earnings — {$ao->reference}", $ao);
                    }
                }
            }
        });

        // ---- A couple of agent coupons ----
        foreach (Agent::where('status', 'active')->take(2)->get() as $i => $agent) {
            Coupon::firstOrCreate(
                ['code' => 'SAVE' . strtoupper(Str::random(4))],
                [
                    'name' => 'Launch discount',
                    'description' => 'Welcome offer from ' . $agent->business_name,
                    'owner_type' => 'agent',
                    'owner_id' => $agent->id,
                    'discount_type' => $i === 0 ? 'percentage' : 'fixed',
                    'discount_value' => $i === 0 ? 10 : 2000,
                    'minimum_order_amount' => 10000,
                    'usage_limit' => 100,
                    'starts_at' => now()->subDay(),
                    'expires_at' => now()->addMonth(),
                    'status' => 'active',
                ]
            );
        }

        // ---- Seed one agent response to a review ----
        Review::where('subject_type', 'listing')->whereNull('response')->first()?->update([
            'response' => 'Thank you for the feedback — we appreciate your business!',
            'responded_at' => Carbon::now(),
        ]);

        // ---- One pending withdrawal request for an agent with available balance ----
        $agent = Agent::where('status', 'active')->with('user')->get()
            ->first(fn ($a) => (float) $a->wallet()->available_balance > 5000);

        if ($agent && ! $agent->withdrawals()->exists()) {
            $bank = $agent->user->bankAccounts()->first();
            $wallet = $wallets->walletFor($agent->user, 'agent');
            $amount = 5000.0;

            $withdrawal = Withdrawal::create([
                'agent_id' => $agent->id,
                'wallet_id' => $wallet->id,
                'bank_account_id' => $bank?->id,
                'amount' => $amount,
                'fee' => 100,
                'net_amount' => $amount - 100,
                'bank_name' => $bank?->bank_name,
                'account_name' => $bank?->account_name,
                'account_number' => $bank?->getAttributes()['account_number'] ?? null,
                'status' => 'pending',
            ]);
            $wallets->debitForSettlement($wallet, $amount, "Withdrawal {$withdrawal->reference}", $withdrawal);
        }
    }
}
