<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Gateways\AsaasGateway;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BillingCheckoutController extends Controller
{
    public function __invoke(Request $request, AsaasGateway $gateway): JsonResponse
    {
        $company = currentCompany();
        abort_if($company === null, Response::HTTP_FORBIDDEN, 'Missing active company context.');

        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $plan = Plan::query()->findOrFail($data['plan_id']);
        $subscription = $company->subscription()->first() ?? $company->subscriptions()->create([
            'plan_id' => $plan->getKey(),
            'started_at' => now(),
            'expires_at' => now()->addDays(config('billing.asaas.default_cycle_days', 30)),
            'status' => Subscription::STATUS_SUSPENDED,
            'gateway_name' => config('billing.gateway'),
        ]);

        $subscription->forceFill([
            'plan_id' => $plan->getKey(),
            'status' => Subscription::STATUS_SUSPENDED,
            'gateway_name' => config('billing.gateway'),
        ])->save();

        $frontendBase = (string) config('app.frontend_url', '');
        $isPublicUrl = filled($frontendBase) && ! str_contains($frontendBase, 'localhost') && ! str_contains($frontendBase, '127.0.0.1');
        $frontendCallbackUrl = $isPublicUrl
            ? rtrim($frontendBase, '/') . '/' . $company->slug . '/configuracoes/plano'
            : null;

        $created = $gateway->createSubscription(
            company: $company,
            plan: $plan,
            dueDate: now()->addDays(3)->toDateString(),
            callbackUrl: $frontendCallbackUrl ?? '',
        );

        $subscription->forceFill([
            'gateway_customer_id' => $created['customer'] ?? $subscription->gateway_customer_id,
            'gateway_subscription_id' => $created['id'] ?? $subscription->gateway_subscription_id,
        ])->save();

        return response()->json([
            'data' => [
                'subscription_id' => $subscription->getKey(),
                'checkout_url' => $created['invoiceUrl'] ?? $created['bankSlipUrl'] ?? null,
                'invoice_url' => $created['invoiceUrl'] ?? null,
                'pdf_url' => $created['bankSlipUrl'] ?? null,
                'payment_hint' => 'A cobranca foi gerada. Finalize o pagamento usando os links abaixo.',
                'subscription_status' => $subscription->status,
            ],
        ]);
    }
}
