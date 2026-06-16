<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class ServiceOrderDeleteController extends Controller
{
    /**
     * Move a cancelled or no-repair service order to the trash (soft delete) after password confirmation.
     *
     * @group Service Orders
     */
    public function __invoke(Request $request, ServiceOrder $serviceOrder): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $company = currentCompany();

        abort_if(
            $serviceOrder->company_id !== $company?->getKey(),
            Response::HTTP_NOT_FOUND,
        );

        $deletableStatuses = [ServiceOrder::STATUS_CANCELLED, ServiceOrder::STATUS_NO_REPAIR];

        if (! in_array($serviceOrder->status, $deletableStatuses, true)) {
            return response()->json([
                'message' => 'Somente ordens canceladas ou sem reparo podem ser movidas para a lixeira.',
                'errors' => ['status' => ['Status inválido para exclusão.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $hasWarrantyReturns = ServiceOrder::query()
            ->where('warranty_origin_service_order_id', $serviceOrder->getKey())
            ->exists();

        if ($hasWarrantyReturns) {
            return response()->json([
                'message' => 'Esta ordem possui retornos de garantia vinculados e não pode ser excluída.',
                'errors' => ['warranty' => ['Há retornos de garantia vinculados a esta ordem.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = currentMembership()?->user;

        abort_if($user === null, Response::HTTP_FORBIDDEN);

        if (! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Senha incorreta.',
                'errors' => ['password' => ['Senha incorreta.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $serviceOrder->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
