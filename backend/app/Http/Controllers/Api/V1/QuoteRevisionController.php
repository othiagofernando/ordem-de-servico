<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\ServiceOrders\Actions\NormalizeQuoteRevisionStates;
use App\Domains\ServiceOrders\Actions\RecordServiceOrderTimelineEvent;
use App\Domains\ServiceOrders\Actions\VisibleServiceOrders;
use App\Domains\ServiceOrders\Models\QuoteRevision;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderTimelineEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class QuoteRevisionController extends Controller
{
    /**
     * List quote revisions for a visible service order.
     *
     * @group Service Orders
     */
    public function index(
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        NormalizeQuoteRevisionStates $normalizeQuoteRevisionStates,
    ): JsonResponse {
        $visible = $this->resolveVisibleServiceOrder($serviceOrder, $visibleServiceOrders);
        $quotes = $normalizeQuoteRevisionStates->handle($visible);

        return response()->json([
            'data' => $quotes->map(fn (QuoteRevision $quote): array => $this->serializeQuote($quote))->all(),
        ]);
    }

    /**
     * Create a new quote revision for a visible service order.
     *
     * @group Service Orders
     */
    public function store(
        Request $request,
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        NormalizeQuoteRevisionStates $normalizeQuoteRevisionStates,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
    ): JsonResponse {
        $visible = $this->resolveVisibleServiceOrder($serviceOrder, $visibleServiceOrders);
        $membership = currentMembership();

        if ($membership === null) {
            return response()->json(['message' => 'Missing active membership context.'], Response::HTTP_FORBIDDEN);
        }

        if (! $membership->hasPermission('service_orders.manage')) {
            return response()->json([
                'message' => 'This membership cannot manage service orders.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($visible->diagnosis_completed_at === null) {
            throw ValidationException::withMessages([
                'diagnosis_completed_at' => 'Diagnosis must be completed before sending a quote.',
            ]);
        }

        $payload = $request->validate([
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price_cents' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'valid_until' => ['required', 'date'],
        ]);

        $quotes = $normalizeQuoteRevisionStates->handle($visible);

        if ($quotes->firstWhere('status', QuoteRevision::STATUS_PENDING) !== null) {
            return response()->json([
                'message' => 'A pending quote already exists for this service order.',
            ], Response::HTTP_CONFLICT);
        }

        $quote = DB::transaction(function () use ($visible, $payload, $membership, $recordTimelineEvent): QuoteRevision {
            $nextRevisionNumber = ((int) $visible->quoteRevisions()->max('revision_number')) + 1;
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
                'company_id' => $visible->company_id,
                'service_order_id' => $visible->getKey(),
                'revision_number' => $nextRevisionNumber,
                'status' => QuoteRevision::STATUS_PENDING,
                'line_items' => $lineItems,
                'currency' => strtoupper((string) ($payload['currency'] ?? 'BRL')),
                'total_cents' => $totalCents,
                'notes' => $payload['notes'] ?? null,
                'valid_until' => $payload['valid_until'],
                'submitted_at' => now(),
            ]);

            $recordTimelineEvent->handle(
                $visible,
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
                ],
            );

            return $quote;
        });

        return response()->json([
            'data' => $this->serializeQuote($quote),
        ], Response::HTTP_CREATED);
    }

    protected function resolveVisibleServiceOrder(
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
    ): ServiceOrder {
        $membership = currentMembership();

        if ($membership === null) {
            abort(Response::HTTP_FORBIDDEN, 'Missing active membership context.');
        }

        $visible = $visibleServiceOrders->queryFor($membership)
            ->whereKey($serviceOrder->getKey())
            ->first();

        abort_if($visible === null, Response::HTTP_NOT_FOUND);

        return $visible;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeQuote(QuoteRevision $quote): array
    {
        return [
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
            'decisioned_at' => $quote->decisioned_at?->toIso8601String(),
            'decision_token_hash' => $quote->decision_token_hash,
            'decision_ip' => $quote->decision_ip,
            'decision_user_agent' => $quote->decision_user_agent,
            'decision_idempotency_key' => $quote->decision_idempotency_key,
        ];
    }
}
