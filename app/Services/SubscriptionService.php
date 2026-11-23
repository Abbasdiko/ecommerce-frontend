<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentSubscription;
use App\Models\ServicePackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionService
{
    /**
     * Create a pending subscription (spec §22, §23). Payment is verified separately.
     */
    public function createPending(Agent $agent, ServicePackage $package): AgentSubscription
    {
        return AgentSubscription::create([
            'subscription_reference' => 'SUB-' . strtoupper(Str::random(10)),
            'agent_id' => $agent->id,
            'service_id' => $package->service_id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
    }

    /**
     * Activate a subscription after server-side payment verification (spec §23).
     * In this milestone the payment gateway is mocked; the reference is still recorded.
     */
    public function activate(AgentSubscription $subscription, string $paymentReference): AgentSubscription
    {
        return DB::transaction(function () use ($subscription, $paymentReference) {
            $subscription->refresh();

            if ($subscription->status === 'active') {
                return $subscription;
            }

            $days = $subscription->package->duration_days ?? 30;

            $subscription->update([
                'status' => 'active',
                'payment_status' => 'successful',
                'payment_reference' => $paymentReference,
                'start_date' => now(),
                'end_date' => now()->addDays($days),
            ]);

            return $subscription;
        });
    }

    public function cancel(AgentSubscription $subscription): AgentSubscription
    {
        $subscription->update(['status' => 'cancelled', 'auto_renew' => false]);

        return $subscription;
    }

    /**
     * Expire all subscriptions past end_date (spec §24 — run by scheduler/command).
     *
     * @return int number of subscriptions expired
     */
    public function expireDue(): int
    {
        $due = AgentSubscription::where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->get();

        foreach ($due as $subscription) {
            $subscription->update(['status' => 'expired']);
        }

        return $due->count();
    }
}
