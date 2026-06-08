<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\ServiceOrders\Actions\VisibleServiceOrders;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ServiceOrderController extends Controller
{
    /**
     * List tenant-scoped service orders visible to the active membership.
     *
     * @group Service Orders
     *
     * Applies the first record-scope rule: technicians only see service orders assigned to them.
     */
    public function index(VisibleServiceOrders $visibleServiceOrders): JsonResponse
    {
        $membership = currentMembership();

        if ($membership === null) {
            return response()->json(['message' => 'Missing active membership context.'], Response::HTTP_FORBIDDEN);
        }

        if (
            ! $membership->hasPermission('service_orders.view_any')
            && ! $membership->hasPermission('service_orders.view_assigned')
        ) {
            return response()->json([
                'message' => 'This membership cannot view service orders.',
            ], Response::HTTP_FORBIDDEN);
        }

        $serviceOrders = $visibleServiceOrders->queryFor($membership)->get();

        return response()->json([
            'data' => $serviceOrders->map(fn (ServiceOrder $serviceOrder): array => [
                'id' => $serviceOrder->getKey(),
                'reference' => $serviceOrder->reference,
                'title' => $serviceOrder->title,
                'status' => $serviceOrder->status,
                'assigned_membership_id' => $serviceOrder->assigned_membership_id,
            ])->all(),
        ]);
    }

    /**
     * Return one visible service order.
     *
     * @group Service Orders
     *
     * Returns 404 when the service order exists in the tenant but is outside the membership record scope.
     */
    public function show(ServiceOrder $serviceOrder, VisibleServiceOrders $visibleServiceOrders): JsonResponse
    {
        $membership = currentMembership();

        if ($membership === null) {
            return response()->json(['message' => 'Missing active membership context.'], Response::HTTP_FORBIDDEN);
        }

        $visible = $visibleServiceOrders->queryFor($membership)
            ->whereKey($serviceOrder->getKey())
            ->first();

        abort_if($visible === null, Response::HTTP_NOT_FOUND);

        return response()->json([
            'data' => [
                'id' => $visible->getKey(),
                'reference' => $visible->reference,
                'title' => $visible->title,
                'status' => $visible->status,
                'assigned_membership_id' => $visible->assigned_membership_id,
            ],
        ]);
    }
}
