<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\ServiceOrders\Actions\NormalizeQuoteRevisionStates;
use App\Domains\ServiceOrders\Actions\RecordServiceOrderTimelineEvent;
use App\Domains\ServiceOrders\Models\QuoteRevision;
use App\Domains\ServiceOrders\Models\ServiceOrderTimelineEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerQuotePortalController extends Controller
{
    /**
     * View one service order and its current quote through a portal token.
     *
     * @group Customer Portal
     */
    public function show(string $token, NormalizeQuoteRevisionStates $normalizeQuoteRevisionStates): JsonResponse
    {
        $quote = $this->resolveQuoteByPortalToken($token);
        $normalizeQuoteRevisionStates->handle($quote->serviceOrder);
        $quote->refresh();

        return response()->json([
            'data' => $this->serializePortalQuote($quote),
        ]);
    }

    /**
     * Approve or reject a quote through a portal token.
     *
     * @group Customer Portal
     */
    public function decide(
        Request $request,
        string $token,
        NormalizeQuoteRevisionStates $normalizeQuoteRevisionStates,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
    ): JsonResponse {
        $quote = $this->resolveQuoteByPortalToken($token);
        $normalizeQuoteRevisionStates->handle($quote->serviceOrder);
        $quote->refresh();

        $payload = $request->validate([
            'decision' => ['required', 'string', 'in:approve,reject'],
        ]);

        if ($quote->status === QuoteRevision::STATUS_EXPIRED) {
            return response()->json([
                'message' => 'This quote is no longer pending for customer decision.',
            ], Response::HTTP_CONFLICT);
        }

        $requestedStatus = $payload['decision'] === 'approve'
            ? QuoteRevision::STATUS_APPROVED
            : QuoteRevision::STATUS_REJECTED;

        if (in_array($quote->status, [QuoteRevision::STATUS_APPROVED, QuoteRevision::STATUS_REJECTED], true)) {
            if ($quote->status === $requestedStatus) {
                return response()->json([
                    'data' => $this->serializePortalQuote($quote),
                ]);
            }

            return response()->json([
                'message' => 'This quote already has a final customer decision.',
            ], Response::HTTP_CONFLICT);
        }

        if ($quote->status !== QuoteRevision::STATUS_PENDING) {
            return response()->json([
                'message' => 'This quote is no longer pending for customer decision.',
            ], Response::HTTP_CONFLICT);
        }

        $quote->forceFill([
            'status' => $requestedStatus,
            'decisioned_at' => now(),
            'decision_token_hash' => hash('sha256', $token),
            'decision_ip' => $request->ip(),
            'decision_user_agent' => (string) $request->userAgent(),
            'decision_idempotency_key' => (string) $request->header('Idempotency-Key', ''),
        ])->save();

        $recordTimelineEvent->handle(
            $quote->serviceOrder,
            type: $requestedStatus === QuoteRevision::STATUS_APPROVED ? 'quote_approved' : 'quote_rejected',
            title: $requestedStatus === QuoteRevision::STATUS_APPROVED ? 'Quote approved' : 'Quote rejected',
            visibility: ServiceOrderTimelineEvent::VISIBILITY_CUSTOMER,
            actorMembership: null,
            payload: [
                'quote_revision_id' => $quote->getKey(),
                'revision_number' => $quote->revision_number,
                'decisioned_at' => $quote->decisioned_at?->toIso8601String(),
                'decision_ip' => $quote->decision_ip,
            ],
        );

        return response()->json([
            'data' => $this->serializePortalQuote($quote->fresh()),
        ]);
    }

    protected function resolveQuoteByPortalToken(string $token): QuoteRevision
    {
        $quote = QuoteRevision::query()
            ->with('serviceOrder')
            ->where('portal_token_hash', hash('sha256', $token))
            ->first();

        if ($quote === null || $quote->portal_token_expires_at === null || $quote->portal_token_expires_at->isPast()) {
            abort(Response::HTTP_NOT_FOUND, 'Invalid or expired portal token.');
        }

        return $quote;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializePortalQuote(QuoteRevision $quote): array
    {
        return [
            'service_order' => [
                'id' => $quote->serviceOrder->getKey(),
                'reference' => $quote->serviceOrder->reference,
                'status' => $quote->serviceOrder->status,
            ],
            'quote' => [
                'id' => $quote->getKey(),
                'revision_number' => $quote->revision_number,
                'status' => $quote->status,
                'line_items' => $quote->line_items,
                'currency' => $quote->currency,
                'total_cents' => $quote->total_cents,
                'notes' => $quote->notes,
                'valid_until' => $quote->valid_until?->toIso8601String(),
                'submitted_at' => $quote->submitted_at?->toIso8601String(),
                'decisioned_at' => $quote->decisioned_at?->toIso8601String(),
            ],
        ];
    }
}
