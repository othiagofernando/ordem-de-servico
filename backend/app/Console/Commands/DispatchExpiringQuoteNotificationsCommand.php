<?php

namespace App\Console\Commands;

use App\Domains\Notifications\Actions\QueueCriticalServiceOrderNotification;
use App\Domains\ServiceOrders\Models\QuoteRevision;
use Illuminate\Console\Command;

class DispatchExpiringQuoteNotificationsCommand extends Command
{
    protected $signature = 'notifications:dispatch-expiring-quotes';

    protected $description = 'Queue customer notifications for pending quotes that are nearing expiration.';

    public function handle(QueueCriticalServiceOrderNotification $queueCriticalServiceOrderNotification): int
    {
        QuoteRevision::query()
            ->with(['serviceOrder.customerContact', 'serviceOrder.company'])
            ->where('status', QuoteRevision::STATUS_PENDING)
            ->whereBetween('valid_until', [now(), now()->copy()->addDay()])
            ->orderBy('valid_until')
            ->get()
            ->each(function (QuoteRevision $quote) use ($queueCriticalServiceOrderNotification): void {
                $queueCriticalServiceOrderNotification->handle(
                    $quote->serviceOrder,
                    'quote_expiring',
                    [
                        'service_order_reference' => $quote->serviceOrder->reference,
                        'quote_revision_id' => $quote->getKey(),
                        'revision_number' => $quote->revision_number,
                        'valid_until' => $quote->valid_until?->toIso8601String(),
                        'total_cents' => $quote->total_cents,
                        'currency' => $quote->currency,
                    ],
                    $quote,
                    dedupeKey: 'quote-expiring:'.$quote->getKey(),
                );
            });

        return self::SUCCESS;
    }
}
