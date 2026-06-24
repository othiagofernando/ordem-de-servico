<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Companies\Actions\ResolveCompanyOperationalSettings;
use App\Domains\ServiceOrders\Actions\CreateQuoteRevision;
use App\Domains\Notifications\Actions\QueueCriticalServiceOrderNotification;
use App\Domains\ServiceOrders\Actions\IssueQuotePortalToken;
use App\Domains\ServiceOrders\Actions\NormalizeQuoteRevisionStates;
use App\Domains\ServiceOrders\Actions\RecordServiceOrderTimelineEvent;
use App\Domains\ServiceOrders\Actions\ResolveVisibleServiceOrder;
use App\Domains\ServiceOrders\Actions\SerializeQuoteRevision;
use App\Domains\ServiceOrders\Actions\VisibleServiceOrders;
use App\Domains\ServiceOrders\Models\QuoteRevision;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderTimelineEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        ResolveVisibleServiceOrder $resolveVisibleServiceOrder,
        SerializeQuoteRevision $serializeQuoteRevision,
    ): JsonResponse {
        $visible = $resolveVisibleServiceOrder->handle($serviceOrder, $visibleServiceOrders);
        $quotes = $normalizeQuoteRevisionStates->handle($visible);

        return response()->json([
            'data' => $quotes->map(fn (QuoteRevision $quote): array => $serializeQuoteRevision->handle($quote))->all(),
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
        IssueQuotePortalToken $issueQuotePortalToken,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
        QueueCriticalServiceOrderNotification $queueCriticalServiceOrderNotification,
        ResolveCompanyOperationalSettings $resolveCompanyOperationalSettings,
        ResolveVisibleServiceOrder $resolveVisibleServiceOrder,
        CreateQuoteRevision $createQuoteRevision,
        SerializeQuoteRevision $serializeQuoteRevision,
    ): JsonResponse {
        $visible = $resolveVisibleServiceOrder->handle($serviceOrder, $visibleServiceOrders);
        $membership = $resolveVisibleServiceOrder->membership();

        if ($membership === null) {
            return response()->json(['message' => 'Missing active membership context.'], Response::HTTP_FORBIDDEN);
        }

        if (! $membership->hasPermission('service_orders.manage')) {
            return response()->json([
                'message' => 'This membership cannot manage service orders.',
            ], Response::HTTP_FORBIDDEN);
        }

        $payload = $request->validate([
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price_cents' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'valid_until' => ['sometimes', 'nullable', 'date'],
        ]);

        $quotes = $normalizeQuoteRevisionStates->handle($visible);

        if ($quotes->firstWhere('status', QuoteRevision::STATUS_PENDING) !== null) {
            return response()->json([
                'message' => 'A pending quote already exists for this service order.',
            ], Response::HTTP_CONFLICT);
        }

        $visible->loadMissing('company');
        $settings = $resolveCompanyOperationalSettings->handle($visible->company);
        $validUntil = filled($payload['valid_until'] ?? null)
            ? $payload['valid_until']
            : now()->addDays($settings->default_quote_validity_days)->toIso8601String();

        $quote = $createQuoteRevision->handle(
            $visible,
            $payload,
            $membership,
            $validUntil,
            $issueQuotePortalToken,
            $recordTimelineEvent,
        );

        $this->queueQuoteAvailableNotification(
            $queueCriticalServiceOrderNotification,
            $visible->fresh(['customerContact', 'company']),
            $quote->fresh(),
        );

        return response()->json([
            'data' => $serializeQuoteRevision->handle($quote, includePlainTextPortalToken: true),
        ], Response::HTTP_CREATED);
    }

    protected function queueQuoteAvailableNotification(
        QueueCriticalServiceOrderNotification $queueCriticalServiceOrderNotification,
        ServiceOrder $serviceOrder,
        QuoteRevision $quote,
    ): void {
        $queueCriticalServiceOrderNotification->handle(
            $serviceOrder,
            'quote_available',
            [
                'service_order_reference' => $serviceOrder->reference,
                'quote_revision_id' => $quote->getKey(),
                'revision_number' => $quote->revision_number,
                'valid_until' => $quote->valid_until?->toIso8601String(),
                'total_cents' => $quote->total_cents,
                'currency' => $quote->currency,
            ],
            $quote,
        );
    }

}
