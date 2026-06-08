<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\ServiceOrders\Actions\IssueQuotePortalToken;
use App\Domains\ServiceOrders\Actions\RecordServiceOrderTimelineEvent;
use App\Domains\ServiceOrders\Actions\VisibleServiceOrders;
use App\Domains\ServiceOrders\Models\QuoteRevision;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderTimelineEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class QuotePortalTokenController extends Controller
{
    /**
     * Regenerate the customer portal token for one quote revision.
     *
     * @group Service Orders
     */
    public function __invoke(
        ServiceOrder $serviceOrder,
        QuoteRevision $quoteRevision,
        VisibleServiceOrders $visibleServiceOrders,
        IssueQuotePortalToken $issueQuotePortalToken,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
    ): JsonResponse {
        $membership = currentMembership();

        if ($membership === null) {
            return response()->json(['message' => 'Missing active membership context.'], Response::HTTP_FORBIDDEN);
        }

        if (! $membership->hasPermission('service_orders.manage')) {
            return response()->json([
                'message' => 'This membership cannot manage service orders.',
            ], Response::HTTP_FORBIDDEN);
        }

        $visible = $visibleServiceOrders->queryFor($membership)
            ->whereKey($serviceOrder->getKey())
            ->first();

        abort_if($visible === null, Response::HTTP_NOT_FOUND);
        abort_unless($quoteRevision->service_order_id === $visible->getKey(), Response::HTTP_NOT_FOUND);

        $token = $issueQuotePortalToken->handle($quoteRevision);

        $recordTimelineEvent->handle(
            $visible,
            type: 'quote_portal_token_regenerated',
            title: 'Quote portal token regenerated',
            visibility: ServiceOrderTimelineEvent::VISIBILITY_INTERNAL,
            actorMembership: $membership,
            payload: [
                'quote_revision_id' => $quoteRevision->getKey(),
                'revision_number' => $quoteRevision->revision_number,
                'portal_token_expires_at' => $token['expires_at']->toIso8601String(),
            ],
        );

        return response()->json([
            'data' => [
                'quote_revision_id' => $quoteRevision->getKey(),
                'revision_number' => $quoteRevision->revision_number,
                'portal_token' => $token['plain_text_token'],
                'portal_token_expires_at' => $token['expires_at']->toIso8601String(),
            ],
        ]);
    }
}
