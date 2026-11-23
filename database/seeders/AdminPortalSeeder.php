<?php

namespace Database\Seeders;

use App\Http\Controllers\Api\Admin\SettingsController;
use App\Models\Agent;
use App\Models\CommissionRule;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Service;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminPortalSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Platform settings (defaults from the schema) ----
        foreach (SettingsController::SCHEMA as $key => [$group, $default]) {
            Setting::firstOrCreate(['key' => $key], ['value' => $default, 'group' => $group]);
        }

        // ---- Payment configuration defaults (admin fills in real keys later) ----
        $paymentDefaults = [
            'payments.mode' => 'simulated',
            'payments.paystack.enabled' => true,
            'payments.flutterwave.enabled' => true,
            'payments.remita.enabled' => true,
            'payments.bank_transfer.enabled' => true,
            'payments.pay_on_delivery.enabled' => true,
            'payments.bank_transfer.accounts' => [
                ['bank_name' => 'Zenith Bank', 'account_name' => 'MegaMarket Ltd', 'account_number' => '1010101010'],
            ],
        ];
        foreach ($paymentDefaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value, 'group' => 'payments']);
        }

        // ---- Commission rules ----
        CommissionRule::firstOrCreate(
            ['scope_type' => 'global', 'scope_id' => null],
            ['commission_type' => 'percentage', 'commission_value' => 10, 'status' => 'active'],
        );
        if ($food = Service::where('name', 'Food Vendor')->first()) {
            CommissionRule::firstOrCreate(
                ['scope_type' => 'service', 'scope_id' => $food->id],
                ['commission_type' => 'percentage', 'commission_value' => 12.5, 'status' => 'active'],
            );
        }
        if ($agent = Agent::where('status', 'active')->first()) {
            CommissionRule::firstOrCreate(
                ['scope_type' => 'agent', 'scope_id' => $agent->id],
                ['commission_type' => 'percentage', 'commission_value' => 8, 'status' => 'active'],
            );
        }

        // ---- Disputes on a few orders ----
        $disputeOrders = Order::whereIn('order_status', ['dispatched', 'delivered'])
            ->with('agentOrders')->take(3)->get();
        $categories = ['item_not_received', 'item_damaged', 'refund_delay'];
        foreach ($disputeOrders as $i => $order) {
            $ao = $order->agentOrders->first();
            $dispute = Dispute::firstOrCreate(
                ['order_id' => $order->id, 'raised_by' => $order->customer_id],
                [
                    'agent_order_id' => $ao?->id,
                    'agent_id' => $ao?->agent_id,
                    'category' => $categories[$i % 3],
                    'subject' => 'Issue with order ' . $order->order_number,
                    'detail' => 'The customer reported a problem with this order and is requesting assistance.',
                    'status' => $i === 0 ? 'under_review' : 'open',
                ]
            );
            $dispute->messages()->firstOrCreate(
                ['user_id' => $order->customer_id, 'author_role' => 'customer'],
                ['body' => 'Please help me resolve this — it has been several days.'],
            );
        }

        // ---- Support tickets ----
        $customers = User::role('customer')->take(4)->get();
        $subjects = [
            ['order', 'Where is my order?', 'high'],
            ['payment', 'I was charged twice', 'urgent'],
            ['account', 'Cannot update my address', 'normal'],
            ['general', 'How do coupons work?', 'low'],
        ];
        foreach ($customers as $i => $customer) {
            [$cat, $subject, $priority] = $subjects[$i % 4];
            $ticket = SupportTicket::firstOrCreate(
                ['user_id' => $customer->id, 'subject' => $subject],
                [
                    'category' => $cat,
                    'priority' => $priority,
                    'status' => $i === 0 ? 'pending' : 'open',
                    'last_reply_at' => now()->subHours($i * 5),
                ]
            );
            $ticket->messages()->firstOrCreate(
                ['user_id' => $customer->id, 'is_staff' => false],
                ['body' => "Hi, I need help with: {$subject}."],
            );
            if ($i === 0) {
                $support = User::role('support')->first();
                if ($support) {
                    $ticket->messages()->firstOrCreate(
                        ['user_id' => $support->id, 'is_staff' => true],
                        ['body' => 'Thanks for reaching out — we are looking into this now.'],
                    );
                }
            }
        }
    }
}
