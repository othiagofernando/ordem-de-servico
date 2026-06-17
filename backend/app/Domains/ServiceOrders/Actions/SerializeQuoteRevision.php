<?php

namespace App\Domains\ServiceOrders\Actions;

use App\Domains\ServiceOrders\Models\QuoteRevision;

class SerializeQuoteRevision
{
    /**
     * @return array<string, mixed>
     */
    public function handle(QuoteRevision $quote, bool $includePlainTextPortalToken = false): array
    {
        $data = [
            'id' => $quote->getKey(),
            'service_order_id' => $quote->service_order_id,
            'revision_number' => $quote->revision_number,
            'status' => $quote->status,
            'line_items' => $quote->line_items,
            'currency' => $quote->currency,
            'total_cents' => $quote->total_cents,
            'notes' => $quote->notes,
            'valid_until' => $quote->valid_until?->toIso8601String(),
            'submitted_at' => $quote->submitted_at?->toIso8601String(),
            'portal_token_expires_at' => $quote->portal_token_expires_at?->toIso8601String(),
            'decisioned_at' => $quote->decisioned_at?->toIso8601String(),
            'decision_token_hash' => $quote->decision_token_hash,
            'decision_ip' => $quote->decision_ip,
            'decision_user_agent' => $quote->decision_user_agent,
            'decision_idempotency_key' => $quote->decision_idempotency_key,
        ];

        if ($includePlainTextPortalToken) {
            $data['portal_token'] = $quote->plain_text_portal_token ?? null;
        }

        return $data;
    }
}
