<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Notifications\Models\NotificationDelivery;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class ServiceOrderForceDeleteController extends Controller
{
    /**
     * Permanently and irreversibly delete a trashed service order and all its data.
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

        DB::transaction(function () use ($serviceOrder): void {
            NotificationDelivery::query()
                ->withoutGlobalScopes()
                ->where('service_order_id', $serviceOrder->getKey())
                ->delete();

            $serviceOrder->timelineEvents()->delete();
            $serviceOrder->executionEntries()->delete();
            $serviceOrder->payments()->delete();
            $serviceOrder->charges()->delete();
            $serviceOrder->quoteRevisions()->delete();
            $serviceOrder->attachments()->delete();
            $serviceOrder->printSnapshot()?->forceDelete();
            $serviceOrder->forceDelete();
        });

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
