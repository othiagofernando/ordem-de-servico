<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Gateways\AsaasGateway;
use App\Domains\Billing\Models\Subscription;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BillingSubscriptionCancelController extends Controller
{
    public function __invoke(Request $request, AsaasGateway $gateway): JsonResponse
    {
        $company = currentCompany();
        abort_if($company === null, Response::HTTP_FORBIDDEN, 'Missing active company context.');

        $subscription = $company->subscription()->first();
        abort_if($subscription === null, Response::HTTP_UNPROCESSABLE_ENTITY, 'A empresa não possui assinatura para cancelar.');

        $data = $request->validate([
            'confirmation' => ['required', 'string'],
        ]);

        abort_if($data['confirmation'] !== $company->name, Response::HTTP_UNPROCESSABLE_ENTITY, 'Confirmação inválida.');

        $gateway->cancelSubscription($subscription);

        $subscription->forceFill([
            'status' => Subscription::STATUS_CANCELLED,
        ])->save();

        return response()->json([
            'data' => ['status' => $subscription->status],
        ]);
    }
}
