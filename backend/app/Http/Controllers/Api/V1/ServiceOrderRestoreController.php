<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ServiceOrderRestoreController extends Controller
{
    /**
     * Restore a trashed service order.
     *
     * @group Service Orders
     */
    public function __invoke(int $serviceOrderId): JsonResponse
    {
        $company = currentCompany();
        abort_if($company === null, Response::HTTP_FORBIDDEN);

        $serviceOrder = ServiceOrder::query()
            ->withTrashed()
            ->where('company_id', $company->getKey())
            ->findOrFail($serviceOrderId);

        abort_if(! $serviceOrder->trashed(), Response::HTTP_UNPROCESSABLE_ENTITY);

        $serviceOrder->restore();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
