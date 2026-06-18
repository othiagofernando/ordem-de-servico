<?php

namespace App\Http\Middleware;

use App\Domains\Billing\Actions\ResolveCurrentSubscription;
use App\Domains\Billing\Actions\SerializeSubscription;
use App\Domains\Billing\Support\CurrentSubscriptionContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function __construct(
        protected ResolveCurrentSubscription $resolveCurrentSubscription,
        protected SerializeSubscription $serializeSubscription,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $company = currentCompany();

        if ($company === null) {
            return $next($request);
        }

        $subscription = $this->resolveCurrentSubscription->handle($company);
        app(CurrentSubscriptionContext::class)->set($subscription);

        if ($subscription === null) {
            return response()->json([
                'message' => 'A empresa não possui assinatura ativa configurada. Entre em contato com o suporte.',
                'subscription' => null,
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        if (! $subscription->isAccessible()) {
            return response()->json([
                'message' => 'A assinatura da empresa está suspensa ou vencida. Entre em contato com o suporte para regularização.',
                'subscription' => $this->serializeSubscription->handle($subscription),
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        return $next($request);
    }
}
