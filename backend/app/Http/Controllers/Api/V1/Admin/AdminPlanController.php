<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Billing\Models\Plan;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AdminPlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Plan::query()
                ->withCount([
                    'subscriptions as subscribed_companies_count',
                    'subscriptions as active_companies_count' => fn ($query) => $query->where('status', 'active'),
                    'subscriptions as expiring_next_15_days_count' => fn ($query) => $query
                        ->where('status', 'active')
                        ->whereBetween('expires_at', [now(), now()->copy()->addDays(15)]),
                ])
                ->orderBy('id')
                ->get()
                ->map(fn (Plan $plan): array => [
                    'id' => $plan->getKey(),
                    'slug' => $plan->slug,
                    'name' => $plan->name,
                    'limits' => [
                        'max_users' => $plan->max_users,
                        'max_service_orders_per_month' => $plan->max_service_orders_per_month,
                        'max_attachments_storage_mb' => $plan->max_attachments_storage_mb,
                    ],
                    'reference_price_cents' => $plan->reference_price_cents,
                    'subscribed_companies_count' => $plan->subscribed_companies_count,
                    'active_companies_count' => $plan->active_companies_count,
                    'expiring_next_15_days_count' => $plan->expiring_next_15_days_count,
                ])->all(),
        ]);
    }

    public function show(Plan $plan): JsonResponse
    {
        $plan->loadCount([
            'subscriptions as subscribed_companies_count',
            'subscriptions as active_companies_count' => fn ($query) => $query->where('status', 'active'),
            'subscriptions as expiring_next_15_days_count' => fn ($query) => $query
                ->where('status', 'active')
                ->whereBetween('expires_at', [now(), now()->copy()->addDays(15)]),
        ]);

        return response()->json([
            'data' => $this->serializePlan($plan),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $plan = Plan::query()->create($this->validatePayload($request));
        $plan->loadCount([
            'subscriptions as subscribed_companies_count',
            'subscriptions as active_companies_count' => fn ($query) => $query->where('status', 'active'),
            'subscriptions as expiring_next_15_days_count' => fn ($query) => $query
                ->where('status', 'active')
                ->whereBetween('expires_at', [now(), now()->copy()->addDays(15)]),
        ]);

        return response()->json([
            'data' => $this->serializePlan($plan),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, Plan $plan): JsonResponse
    {
        $plan->fill($this->validatePayload($request, $plan))->save();
        $plan->loadCount([
            'subscriptions as subscribed_companies_count',
            'subscriptions as active_companies_count' => fn ($query) => $query->where('status', 'active'),
            'subscriptions as expiring_next_15_days_count' => fn ($query) => $query
                ->where('status', 'active')
                ->whereBetween('expires_at', [now(), now()->copy()->addDays(15)]),
        ]);

        return response()->json([
            'data' => $this->serializePlan($plan),
        ]);
    }

    public function destroy(Plan $plan): JsonResponse
    {
        $activeCount = $plan->subscriptions()->where('status', 'active')->count();

        if ($activeCount > 0) {
            throw new UnprocessableEntityHttpException(
                "Não é possível excluir este plano: {$activeCount} empresa(s) com assinatura ativa."
            );
        }

        $plan->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializePlan(Plan $plan): array
    {
        return [
            'id' => $plan->getKey(),
            'slug' => $plan->slug,
            'name' => $plan->name,
            'limits' => [
                'max_users' => $plan->max_users,
                'max_service_orders_per_month' => $plan->max_service_orders_per_month,
                'max_attachments_storage_mb' => $plan->max_attachments_storage_mb,
            ],
            'reference_price_cents' => $plan->reference_price_cents,
            'subscribed_companies_count' => $plan->subscribed_companies_count ?? 0,
            'active_companies_count' => $plan->active_companies_count ?? 0,
            'expiring_next_15_days_count' => $plan->expiring_next_15_days_count ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePayload(Request $request, ?Plan $plan = null): array
    {
        return $request->validate([
            'slug' => [
                'required',
                'string',
                'max:80',
                Rule::unique('plans', 'slug')->ignore($plan?->getKey()),
            ],
            'name' => ['required', 'string', 'max:120'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_service_orders_per_month' => ['nullable', 'integer', 'min:1'],
            'max_attachments_storage_mb' => ['nullable', 'integer', 'min:1'],
            'reference_price_cents' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
