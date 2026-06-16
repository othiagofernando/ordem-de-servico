<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ServiceOrderTrashController extends Controller
{
    /**
     * List soft-deleted (trashed) service orders for the current company.
     *
     * @group Service Orders
     */
    public function __invoke(): JsonResponse
    {
        abort_if(currentCompany() === null, Response::HTTP_FORBIDDEN);

        $orders = ServiceOrder::query()
            ->onlyTrashed()
            ->with(['customer', 'customerContact', 'equipment'])
            ->orderByDesc('deleted_at')
            ->paginate(30);

        $data = $orders->map(fn (ServiceOrder $so) => [
            'id' => $so->getKey(),
            'reference' => $so->reference,
            'title' => $so->title,
            'status' => $so->status,
            'customer_name' => $so->customer?->name,
            'equipment_brand' => $so->equipment?->brand,
            'equipment_model' => $so->equipment?->model,
            'equipment_serial_number' => $so->equipment?->serial_number,
            'deleted_at' => $so->deleted_at?->toIso8601String(),
        ]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $orders->total(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }
}
