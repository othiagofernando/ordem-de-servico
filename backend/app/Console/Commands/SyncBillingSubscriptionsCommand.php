<?php

namespace App\Console\Commands;

use App\Domains\Billing\Actions\DispatchBillingNotification;
use App\Domains\Billing\Actions\SerializeSubscription;
use App\Domains\Billing\Gateways\AsaasGateway;
use App\Domains\Billing\Models\Subscription;
use Illuminate\Console\Command;

class SyncBillingSubscriptionsCommand extends Command
{
    protected $signature = 'billing:sync-subscriptions';

    protected $description = 'Synchronize billing subscriptions and renew reminders.';

    public function handle(
        AsaasGateway $gateway,
        SerializeSubscription $serializeSubscription,
        DispatchBillingNotification $dispatchBillingNotification,
    ): int
    {
        Subscription::query()
            ->with(['company', 'plan'])
            ->whereNotNull('gateway_subscription_id')
            ->chunkById(50, function ($subscriptions) use ($gateway, $serializeSubscription): void {
                foreach ($subscriptions as $subscription) {
                    $remote = $gateway->fetchSubscription($subscription->gateway_subscription_id);
                    $status = strtolower((string) ($remote['status'] ?? $subscription->status));

                    $subscription->forceFill([
                        'status' => match ($status) {
                            'active', 'paid', 'confirmed' => Subscription::STATUS_ACTIVE,
                            'cancelled', 'deleted' => Subscription::STATUS_CANCELLED,
                            default => Subscription::STATUS_SUSPENDED,
                        },
                    ])->save();

                    $this->line(sprintf(
                        '%s => %s',
                        $subscription->company?->name ?? '#'.$subscription->company_id,
                        json_encode($serializeSubscription->handle($subscription->fresh(['company', 'plan']))),
                    ));
                }
            });

        // Suspend active subscriptions that have passed the grace period (covers trial and manual subscriptions)
        Subscription::query()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('expires_at', '<', now()->subDays(Subscription::GRACE_DAYS))
            ->update(['status' => Subscription::STATUS_SUSPENDED]);

        Subscription::query()
            ->with(['company', 'plan'])
            ->where('status', Subscription::STATUS_ACTIVE)
            ->chunkById(50, function ($subscriptions) use ($dispatchBillingNotification): void {
                foreach ($subscriptions as $subscription) {
                    $daysUntilExpiration = $subscription->daysUntilExpiration();

                    if ($daysUntilExpiration === 15 || $daysUntilExpiration === 3) {
                        $dispatchBillingNotification->sendRenewalReminder($subscription, $daysUntilExpiration);
                    }

                    if ($daysUntilExpiration === 0) {
                        $dispatchBillingNotification->sendSuspensionGraceNotice($subscription);
                    }
                }
            });

        return self::SUCCESS;
    }
}
