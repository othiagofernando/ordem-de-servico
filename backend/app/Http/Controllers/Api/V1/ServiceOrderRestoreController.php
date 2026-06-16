<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class ServiceOrderRestoreController extends Controller
{
    /**
     * Restore a trashed service order after password confirmation.
     *
     * @group Service Orders
     */
    public function __invoke(Request $request, int $serviceOrderId): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $company = currentCompany();
        abort_if($company === null, Response::HTTP_FORBIDDEN);

        $serviceOrder = ServiceOrder::query()
            ->withTrashed()
            ->where('company_id', $company->getKey())
            ->findOrFail($serviceOrderId);

        abort_if(! $serviceOrder->trashed(), Response::HTTP_UNPROCESSABLE_ENTITY);

        $user = currentMembership()?->user;
        abort_if($user === null, Response::HTTP_FORBIDDEN);

        if (! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Senha incorreta.',
                'errors' => ['password' => ['Senha incorreta.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $serviceOrder->restore();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
