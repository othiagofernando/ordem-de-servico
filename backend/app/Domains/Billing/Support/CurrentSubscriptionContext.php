<?php

namespace App\Domains\Billing\Support;

use App\Domains\Billing\Models\Subscription;

class CurrentSubscriptionContext
{
    protected ?Subscription $subscription = null;

    public function set(?Subscription $subscription): void
    {
        $this->subscription = $subscription;
    }

    public function clear(): void
    {
        $this->subscription = null;
    }

    public function subscription(): ?Subscription
    {
        return $this->subscription;
    }
}
