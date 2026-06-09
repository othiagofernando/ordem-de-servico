<?php

namespace App\Http\Middleware;

use App\Support\Logging\StructuredLogContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EstablishStructuredLogContext
{
    public function __construct(
        protected StructuredLogContext $structuredLogContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->structuredLogContext->reset();

        $correlationId = $this->structuredLogContext->ensureCorrelationId($request->header('X-Correlation-Id'));

        $this->structuredLogContext->share([
            'request_method' => $request->method(),
            'request_path' => '/'.$request->path(),
            'request_ip' => $request->ip(),
            'route_name' => $request->route()?->getName(),
            ...$this->extractBusinessIdentifiers($request),
        ]);

        $response = $next($request);

        $this->structuredLogContext->share([
            'route_name' => $request->route()?->getName(),
            'response_status' => $response->getStatusCode(),
            'company_id' => currentCompany()?->getKey(),
            'actor_membership_id' => currentMembership()?->getKey(),
            'actor_user_id' => auth()->id(),
            'api_token_id' => app(\App\Domains\Companies\Support\CurrentCompanyContext::class)->apiToken()?->getKey(),
            ...$this->extractBusinessIdentifiers($request),
        ]);

        Log::info('api.request.completed');

        $response->headers->set('X-Correlation-Id', $correlationId);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractBusinessIdentifiers(Request $request): array
    {
        $parameters = $request->route()?->parameters() ?? [];
        $context = [];

        foreach ($parameters as $name => $value) {
            if ($name === 'token') {
                continue;
            }

            $id = $value instanceof Model ? $value->getKey() : $value;

            $key = match ($name) {
                'serviceOrder' => 'service_order_id',
                'quoteRevision' => 'quote_revision_id',
                'membership' => 'membership_id',
                'customer' => 'customer_id',
                'equipment' => 'equipment_id',
                'attachment' => 'attachment_id',
                default => null,
            };

            if ($key !== null) {
                $context[$key] = $id;
            }
        }

        return $context;
    }
}
