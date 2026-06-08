<?php

namespace App\Domains\ServiceOrders\Actions;

use App\Domains\ServiceOrders\Models\QuoteRevision;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderTimelineEvent;
use Illuminate\Support\Collection;

class NormalizeQuoteRevisionStates
{
    /**
     * @return Collection<int, QuoteRevision>
     */
    public function handle(ServiceOrder $serviceOrder): Collection
    {
        $quotes = $serviceOrder->quoteRevisions()->orderBy('revision_number')->get();

        $quotes->each(function (QuoteRevision $quote) use ($serviceOrder): void {
            if ($quote->status !== QuoteRevision::STATUS_PENDING) {
                return;
            }

            if ($quote->valid_until === null || ! $quote->valid_until->isPast()) {
                return;
            }

            $quote->forceFill([
                'status' => QuoteRevision::STATUS_EXPIRED,
            ])->save();

            app(RecordServiceOrderTimelineEvent::class)->handle(
                $serviceOrder,
                type: 'quote_expired',
                title: 'Quote expired',
                visibility: ServiceOrderTimelineEvent::VISIBILITY_CUSTOMER,
                actorMembership: currentMembership(),
                payload: [
                    'quote_revision_id' => $quote->getKey(),
                    'revision_number' => $quote->revision_number,
                    'valid_until' => $quote->valid_until?->toIso8601String(),
                ],
            );
        });

        return $serviceOrder->quoteRevisions()->orderBy('revision_number')->get();
    }
}
