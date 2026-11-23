<?php

namespace App\Console\Commands;

use App\Models\ReferralCommission;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Console\Command;

class MatureReferralCommissions extends Command
{
    protected $signature = 'commissions:mature';

    protected $description = 'Move pending referral commissions to available once their order is delivered (spec §64)';

    public function handle(WalletService $wallets): int
    {
        $due = ReferralCommission::where('status', 'pending')
            ->whereHas('order', fn ($q) => $q->where('order_status', 'delivered'))
            ->get();

        foreach ($due as $commission) {
            $commission->update(['status' => 'available']);

            $referrer = User::find($commission->referrer_user_id);
            if ($referrer) {
                $wallet = $wallets->walletFor($referrer);
                $wallets->maturePending(
                    $wallet,
                    (float) $commission->commission_amount,
                    "Referral commission matured — order #{$commission->order_id}",
                    $commission,
                );
            }
        }

        $this->info("Matured {$due->count()} referral commission(s).");

        return self::SUCCESS;
    }
}
