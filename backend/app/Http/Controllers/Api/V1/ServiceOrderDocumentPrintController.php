<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\ServiceOrders\Actions\BuildOperationalDocumentPayload;
use App\Domains\ServiceOrders\Actions\NormalizeQuoteRevisionStates;
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
    ): JsonResponse {
        $visible = $this->resolveVisibleServiceOrder($serviceOrder, $visibleServiceOrders);
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
    ): JsonResponse {
        $visible = $this->resolveFinanceVisibleServiceOrder($serviceOrder, $visibleServiceOrders);
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
    ): JsonResponse {
        $visible = $this->resolveVisibleServiceOrder($serviceOrder, $visibleServiceOrders);
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

    protected function resolveFinanceVisibleServiceOrder(
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
    ): ServiceOrder {
        $membership = currentMembership();

        if ($membership === null) {
            abort(Response::HTTP_FORBIDDEN, 'Missing active membership context.');
        }

        if (! $membership->hasPermission('service_orders.finance')) {
            abort(Response::HTTP_FORBIDDEN, 'This membership cannot print payment receipts.');
        }

        return $this->resolveVisibleServiceOrder($serviceOrder, $visibleServiceOrders);
    }
}
