<?php

namespace App\Domains\ServiceOrders\Actions;

use App\Domains\Auth\Models\Membership;
use App\Domains\ServiceOrders\Models\QuoteRevision;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderTimelineEvent;
use Illuminate\Support\Facades\DB;

class CreateQuoteRevision
{
    /**
     * @param  array{line_items: array<int, array{description: string, quantity: int, unit_price_cents: int}>, notes?: string|null, currency?: string|null}  $payload
     */
    public function handle(
        ServiceOrder $serviceOrder,
        array $payload,
        Membership $membership,
        string $validUntil,
        IssueQuotePortalToken $issueQuotePortalToken,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
    ): QuoteRevision {
        return DB::transaction(function () use (
            $serviceOrder,
            $payload,
            $membership,
            $validUntil,
            $issueQuotePortalToken,
            $recordTimelineEvent,
        ): QuoteRevision {
            $nextRevisionNumber = ((int) $serviceOrder->quoteRevisions()->max('revision_number')) + 1;
            $lineItems = collect($payload['line_items'])->map(function (array $item): array {
                return [
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price_cents' => $item['unit_price_cents'],
                    'line_total_cents' => $item['quantity'] * $item['unit_price_cents'],
                ];
            })->all();

            $totalCents = collect($lineItems)->sum('line_total_cents');

            $quote = QuoteRevision::query()->create([
                'company_id' => $serviceOrder->company_id,
                'service_order_id' => $serviceOrder->getKey(),
                'revision_number' => $nextRevisionNumber,
                'status' => QuoteRevision::STATUS_PENDING,
                'line_items' => $lineItems,
                'currency' => strtoupper((string) ($payload['currency'] ?? 'BRL')),
                'total_cents' => $totalCents,
                'notes' => $payload['notes'] ?? null,
                'valid_until' => $validUntil,
                'submitted_at' => now(),
            ]);

            $portalToken = $issueQuotePortalToken->handle($quote);

            $recordTimelineEvent->handle(
                $serviceOrder,
                type: 'quote_submitted',
                title: 'Quote submitted',
                visibility: ServiceOrderTimelineEvent::VISIBILITY_CUSTOMER,
                actorMembership: $membership,
                payload: [
                    'quote_revision_id' => $quote->getKey(),
                    'revision_number' => $quote->revision_number,
                    'status' => $quote->status,
                    'valid_until' => $quote->valid_until?->toIso8601String(),
                    'total_cents' => $quote->total_cents,
                    'currency' => $quote->currency,
                    'portal_token_expires_at' => $portalToken['expires_at']->toIso8601String(),
                ],
            );

            return $quote->forceFill([
                'plain_text_portal_token' => $portalToken['plain_text_token'],
            ]);
        });
    }
}
