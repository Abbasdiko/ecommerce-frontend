<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Expire agent subscriptions past their end date and revoke listing privileges (spec §24)';

    public function handle(SubscriptionService $subscriptions): int
    {
        $count = $subscriptions->expireDue();

        AuditLogger::record('subscriptions.expired_batch', null, [], ['count' => $count]);
        $this->info("Expired {$count} subscription(s).");

        return self::SUCCESS;
    }
}
