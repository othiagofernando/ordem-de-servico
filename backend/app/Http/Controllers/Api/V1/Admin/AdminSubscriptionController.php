<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Billing\Actions\SerializeSubscription;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Companies\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSubscriptionController extends Controller
{
    public function index(Request $request, SerializeSubscription $serializeSubscription): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'string', 'max:120'],
            'status' => ['sometimes', 'string', 'in:active,suspended,cancelled'],
            'plan_id' => ['sometimes', 'integer', 'exists:plans,id'],
            'expiring' => ['sometimes', 'boolean'],
        ]);

        $companies = Company::query()
            ->with(['subscription.plan'])
            ->when(
                filled($filters['q'] ?? null),
                fn ($query) => $query->where(function ($companyQuery) use ($filters): void {
                    $term = trim((string) $filters['q']);

                    $companyQuery->where('name', 'like', '%'.$term.'%')
                        ->orWhere('document', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%');
                })
            )
            ->when(
                filled($filters['status'] ?? null),
                fn ($query) => $query->whereHas('subscription', fn ($subscriptionQuery) => $subscriptionQuery->where('status', $filters['status']))
            )
            ->when(
                isset($filters['plan_id']),
                fn ($query) => $query->whereHas('subscription', fn ($subscriptionQuery) => $subscriptionQuery->where('plan_id', $filters['plan_id']))
            )
            ->when(
                filter_var($filters['expiring'] ?? false, FILTER_VALIDATE_BOOLEAN),
                fn ($query) => $query
                    ->whereHas('subscription', fn ($subscriptionQuery) => $subscriptionQuery
                        ->where('status', Subscription::STATUS_ACTIVE)
                        ->whereBetween('expires_at', [now(), now()->copy()->addDays(15)]))
            )
            ->whereHas('subscription')
            ->get()
            ->sortBy(fn (Company $company) => $company->subscription?->expires_at?->getTimestamp() ?? PHP_INT_MAX)
            ->values();

        return response()->json([
            'data' => $companies->map(function (Company $company) use ($serializeSubscription): array {
                $subscription = $company->subscription;
                $latestInvoice = $subscription->billingInvoices()->latest('id')->first();

                return [
                    'company' => [
                        'id' => $company->getKey(),
                        'name' => $company->name,
                        'document' => $company->document,
                        'email' => $company->email,
                        'status' => $company->status,
                    ],
                    'subscription' => $serializeSubscription->handle($subscription),
                    'latest_invoice' => $latestInvoice ? [
                        'id' => $latestInvoice->getKey(),
                        'status' => $latestInvoice->status,
                        'amount_cents' => $latestInvoice->amount_cents,
                        'currency' => $latestInvoice->currency,
                        'due_at' => $latestInvoice->due_at?->toIso8601String(),
                        'paid_at' => $latestInvoice->paid_at?->toIso8601String(),
                        'invoice_url' => $latestInvoice->invoice_url,
                        'pdf_url' => $latestInvoice->pdf_url,
                    ] : null,
                ];
            })->all(),
            'summary' => [
                'total' => Company::query()->whereHas('subscription')->count(),
                'active' => Company::query()->whereHas('subscription', fn ($query) => $query->where('status', Subscription::STATUS_ACTIVE))->count(),
                'suspended' => Company::query()->whereHas('subscription', fn ($query) => $query->where('status', Subscription::STATUS_SUSPENDED))->count(),
                'cancelled' => Company::query()->whereHas('subscription', fn ($query) => $query->where('status', Subscription::STATUS_CANCELLED))->count(),
                'expiring_next_15_days' => Company::query()
                    ->whereHas('subscription', fn ($query) => $query
                        ->where('status', Subscription::STATUS_ACTIVE)
                        ->whereBetween('expires_at', [now(), now()->copy()->addDays(15)]))
                    ->count(),
            ],
        ]);
    }
}
