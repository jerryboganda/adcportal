<?php

namespace App\Console\Commands;

use App\Services\SupportSessionService;
use App\Services\TenantLifecycleService;
use Illuminate\Console\Command;

/**
 * Daily SaaS housekeeping: lapse finished trials/terms to `expired` and
 * close expired break-glass support sessions. Registered on the scheduler.
 */
class SubscriptionSweep extends Command
{
    protected $signature = 'ris:subscription-sweep';

    protected $description = 'Expire lapsed subscriptions/trials and close expired support sessions';

    public function handle(TenantLifecycleService $lifecycle): int
    {
        $lapsed = $lifecycle->sweepExpiries();
        $closed = SupportSessionService::expireStale();

        $this->info("Swept: {$lapsed} subscription(s) expired, {$closed} support session(s) closed.");

        return self::SUCCESS;
    }
}
