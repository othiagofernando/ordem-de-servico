<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Parts\Models\Part;
use App\Domains\Parts\Models\ServiceOrderPart;
use App\Domains\ServiceOrders\Actions\ResolveVisibleServiceOrder;
use App\Domains\ServiceOrders\Actions\VisibleServiceOrders;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ServiceOrderPartController extends Controller
{
    public function store(
        Request $request,
        ServiceOrder $serviceOrder,
        ResolveVisibleServiceOrder $resolveVisibleServiceOrder,
        VisibleServiceOrders $visibleServiceOrders,
    ): JsonResponse {
        $serviceOrder = $resolveVisibleServiceOrder->handle($serviceOrder, $visibleServiceOrders);

        $data = $request->validate([
            'part_id'  => ['required', 'integer', 'exists:parts,id'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'notes'    => ['nullable', 'string', 'max:500'],
        ]);

        $part = Part::query()->find($data['part_id']);

        abort_if($part === null, Response::HTTP_NOT_FOUND);

        if ($part->company_id !== currentCompany()->getKey()) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ((float) $part->stock_quantity < (float) $data['quantity']) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Estoque insuficiente.');
        }

        $serviceOrderPart = DB::transaction(function () use ($serviceOrder, $part, $data): ServiceOrderPart {
            $sop = ServiceOrderPart::query()->create([
                'company_id'           => currentCompany()->getKey(),
                'service_order_id'     => $serviceOrder->getKey(),
                'part_id'              => $part->getKey(),
                'quantity'             => $data['quantity'],
                'unit_cost_price_cents' => $part->cost_price_cents,
                'unit_sale_price_cents' => $part->sale_price_cents,
                'notes'                => $data['notes'] ?? null,
            ]);

            $part->decrement('stock_quantity', (float) $data['quantity']);

            return $sop;
        });

        return response()->json([
            'data' => [
                'id'                    => $serviceOrderPart->getKey(),
                'service_order_id'      => $serviceOrderPart->service_order_id,
                'part_id'               => $serviceOrderPart->part_id,
                'part_name'             => $part->name,
                'part_sku'              => $part->sku,
                'part_unit'             => $part->unit,
                'quantity'              => (float) $serviceOrderPart->quantity,
                'unit_cost_price_cents' => $serviceOrderPart->unit_cost_price_cents,
                'unit_sale_price_cents' => $serviceOrderPart->unit_sale_price_cents,
                'notes'                 => $serviceOrderPart->notes,
            ],
        ], Response::HTTP_CREATED);
    }

    public function destroy(
        ServiceOrder $serviceOrder,
        ServiceOrderPart $serviceOrderPart,
        ResolveVisibleServiceOrder $resolveVisibleServiceOrder,
        VisibleServiceOrders $visibleServiceOrders,
    ): JsonResponse {
        $resolveVisibleServiceOrder->handle($serviceOrder, $visibleServiceOrders);

        if ((int) $serviceOrderPart->service_order_id !== (int) $serviceOrder->getKey()) {
            abort(Response::HTTP_NOT_FOUND);
        }

        DB::transaction(function () use ($serviceOrderPart): void {
            $part = $serviceOrderPart->part;
            $serviceOrderPart->delete();

            if ($part !== null) {
                $part->increment('stock_quantity', (float) $serviceOrderPart->quantity);
            }
        });

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
