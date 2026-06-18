<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Billing\Actions\SerializeSubscription;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Companies\Models\Company;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AdminOverviewController extends Controller
{
    public function __invoke(SerializeSubscription $serializeSubscription): JsonResponse
    {
        $expiringCompanies = Company::query()
            ->with(['subscription.plan'])
            ->whereHas('subscription', fn ($query) => $query
                ->where('status', Subscription::STATUS_ACTIVE)
                ->whereBetween('expires_at', [now(), now()->copy()->addDays(15)]))
            ->orderByDesc(
                Subscription::query()
                    ->select('expires_at')
                    ->whereColumn('subscriptions.company_id', 'companies.id')
                    ->latest('id')
                    ->limit(1)
            )
            ->limit(5)
            ->get();

        $recentCompanies = Company::query()
            ->with(['subscription.plan'])
            ->latest('created_at')
            ->limit(5)
            ->get();

        $suspendedCompanies = Company::query()
            ->with(['subscription.plan'])
            ->where('status', Company::STATUS_SUSPENDED)
            ->latest('updated_at')
            ->limit(5)
            ->get();

        return response()->json([
            'summary' => [
                'active_companies' => Company::query()
                    ->whereNull('deleted_at')
                    ->where('status', Company::STATUS_ACTIVE)
                    ->count(),
                'service_orders_this_month' => ServiceOrder::query()
                    ->withoutGlobalScopes()
                    ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'new_companies_last_7_days' => Company::query()
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
                'subscriptions_expiring_next_15_days' => Company::query()
                    ->whereHas('subscription', fn ($query) => $query
                        ->where('status', Subscription::STATUS_ACTIVE)
                        ->whereBetween('expires_at', [now(), now()->copy()->addDays(15)]))
                    ->count(),
                'total_plans' => Plan::query()->count(),
                'active_subscriptions' => Company::query()
                    ->whereHas('subscription', fn ($query) => $query->where('status', Subscription::STATUS_ACTIVE))
                    ->count(),
                'suspended_subscriptions' => Company::query()
                    ->whereHas('subscription', fn ($query) => $query->where('status', Subscription::STATUS_SUSPENDED))
                    ->count(),
            ],
            'expiring_companies' => $expiringCompanies->map(fn (Company $company): array => [
                'id' => $company->getKey(),
                'name' => $company->name,
                'document' => $company->document,
                'subscription' => $serializeSubscription->handle($company->subscription),
            ])->all(),
            'recent_companies' => $recentCompanies->map(fn (Company $company): array => [
                'id' => $company->getKey(),
                'name' => $company->name,
                'document' => $company->document,
                'created_at' => $company->created_at?->toIso8601String(),
                'status' => $company->status,
                'subscription' => $serializeSubscription->handle($company->subscription),
            ])->all(),
            'suspended_companies' => $suspendedCompanies->map(fn (Company $company): array => [
                'id' => $company->getKey(),
                'name' => $company->name,
                'document' => $company->document,
                'status' => $company->status,
                'subscription' => $serializeSubscription->handle($company->subscription),
            ])->all(),
        ]);
    }
}
