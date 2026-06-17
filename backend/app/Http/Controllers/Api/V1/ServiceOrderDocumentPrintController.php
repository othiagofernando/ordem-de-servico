<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\ServiceOrders\Actions\BuildOperationalDocumentPayload;
use App\Domains\ServiceOrders\Actions\NormalizeQuoteRevisionStates;
use App\Domains\ServiceOrders\Actions\ResolveFinanceVisibleServiceOrder;
use App\Domains\ServiceOrders\Actions\ResolveVisibleServiceOrder;
use App\Domains\ServiceOrders\Actions\VisibleServiceOrders;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderPayment;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ServiceOrderDocumentPrintController extends Controller
{
    /**
     * Return a printable payload for the current quote revision of a visible service order.
     *
     * @group Service Orders
     */
    public function quote(
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        NormalizeQuoteRevisionStates $normalizeQuoteRevisionStates,
        BuildOperationalDocumentPayload $buildOperationalDocumentPayload,
        ResolveVisibleServiceOrder $resolveVisibleServiceOrder,
    ): JsonResponse {
        $visible = $resolveVisibleServiceOrder->handle($serviceOrder, $visibleServiceOrders);
        $visible->loadMissing(['company', 'customer', 'customerContact', 'equipment']);

        $currentQuote = $normalizeQuoteRevisionStates->handle($visible)
            ->sortByDesc('revision_number')
            ->first();

        if ($currentQuote === null) {
            return response()->json([
                'message' => 'This service order has no quote to print.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => $buildOperationalDocumentPayload->quote($visible, $currentQuote),
        ]);
    }

    /**
     * Return a printable payload for one immutable payment receipt snapshot.
     *
     * @group Service Orders
     */
    public function receipt(
        ServiceOrder $serviceOrder,
        ServiceOrderPayment $payment,
        VisibleServiceOrders $visibleServiceOrders,
        BuildOperationalDocumentPayload $buildOperationalDocumentPayload,
        ResolveVisibleServiceOrder $resolveVisibleServiceOrder,
        ResolveFinanceVisibleServiceOrder $resolveFinanceVisibleServiceOrder,
    ): JsonResponse {
        $visible = $resolveFinanceVisibleServiceOrder->handle($serviceOrder, $visibleServiceOrders, $resolveVisibleServiceOrder);
        $visible->loadMissing(['company', 'customer', 'customerContact']);

        abort_if(
            $payment->service_order_id !== $visible->getKey() || $payment->company_id !== $visible->company_id,
            Response::HTTP_NOT_FOUND,
        );

        return response()->json([
            'data' => $buildOperationalDocumentPayload->receipt($visible, $payment),
        ]);
    }

    /**
     * Return a printable payload for one delivered service order handoff term.
     *
     * @group Service Orders
     */
    public function delivery(
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        BuildOperationalDocumentPayload $buildOperationalDocumentPayload,
        ResolveVisibleServiceOrder $resolveVisibleServiceOrder,
    ): JsonResponse {
        $visible = $resolveVisibleServiceOrder->handle($serviceOrder, $visibleServiceOrders);
        $visible->loadMissing(['company', 'customer', 'customerContact', 'equipment', 'charges', 'payments']);

        if ($visible->status !== ServiceOrder::STATUS_DELIVERED || $visible->delivered_at === null) {
            return response()->json([
                'message' => 'Only delivered service orders can expose a printable delivery term.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'data' => $buildOperationalDocumentPayload->delivery($visible),
        ]);
    }
}
