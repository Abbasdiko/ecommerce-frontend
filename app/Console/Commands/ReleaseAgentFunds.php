<?php

namespace App\Console\Commands;

use App\Models\AgentOrder;
use App\Services\WalletService;
use Illuminate\Console\Command;

class ReleaseAgentFunds extends Command
{
    protected $signature = 'wallet:release-agent-funds';

    protected $description = 'Move delivered agent earnings from pending to available after the hold window (spec §64)';

    public function handle(WalletService $wallets): int
    {
        $holdDays = (int) config('payments.withdrawals.agent_fund_hold_days', 3);

        $due = AgentOrder::where('status', 'delivered')
            ->whereNotNull('delivered_at')
            ->whereNull('funds_released_at')
            ->where('delivered_at', '<=', now()->subDays($holdDays))
            ->with('agent.user')
            ->get();

        $released = 0;
        foreach ($due as $agentOrder) {
            if (! $agentOrder->agent || (float) $agentOrder->agent_earning <= 0) {
                $agentOrder->update(['funds_released_at' => now()]);
                continue;
            }

            $wallet = $wallets->walletFor($agentOrder->agent->user, 'agent');
            $wallets->maturePending(
                $wallet,
                (float) $agentOrder->agent_earning,
                "Funds released — {$agentOrder->reference}",
                $agentOrder,
            );
            $agentOrder->update(['funds_released_at' => now()]);
            $released++;
        }

        $this->info("Released funds for {$released} agent order(s).");

        return self::SUCCESS;
    }
}
