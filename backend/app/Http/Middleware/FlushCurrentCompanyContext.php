<?php

namespace App\Http\Middleware;

use App\Domains\Billing\Support\CurrentSubscriptionContext;
use App\Domains\Companies\Support\CurrentCompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FlushCurrentCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        app(CurrentCompanyContext::class)->clear();
        app(CurrentSubscriptionContext::class)->clear();

        $response = $next($request);

        app(CurrentCompanyContext::class)->clear();
        app(CurrentSubscriptionContext::class)->clear();

        return $response;
    }
}
