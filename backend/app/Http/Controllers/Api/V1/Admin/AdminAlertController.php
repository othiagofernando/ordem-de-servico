<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Auditing\Models\BusinessAudit;
use App\Domains\Billing\Models\BillingInvoice;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Companies\Models\Company;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminAlertController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $overdueInvoices = BillingInvoice::query()
            ->with(['company', 'subscription.plan'])
            ->whereNull('paid_at')
            ->whereDate('due_at', '<', now()->toDateString())
            ->latest('due_at')
            ->limit(10)
            ->get();

        $expiringSubscriptions = Company::query()
            ->with(['subscription.plan'])
            ->whereHas('subscription', fn ($query) => $query
                ->where('status', Subscription::STATUS_ACTIVE)
                ->whereBetween('expires_at', [now(), now()->copy()->addDays(15)]))
            ->limit(10)
            ->get()
            ->sortBy(fn (Company $company) => $company->subscription?->expires_at?->getTimestamp() ?? PHP_INT_MAX)
            ->values();

        $suspendedCompanies = Company::query()
            ->with(['subscription.plan'])
            ->where('status', Company::STATUS_SUSPENDED)
            ->latest('updated_at')
            ->limit(10)
            ->get();

        $adminAudits = BusinessAudit::query()
            ->withoutGlobalScopes()
            ->with(['actorMembership.user', 'company'])
            ->where('category', 'admin')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $actorUserIds = $adminAudits
            ->pluck('context')
            ->map(fn ($context) => is_array($context) ? ($context['actor_user_id'] ?? null) : null)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $superAdminNames = User::query()->whereIn('id', $actorUserIds)->pluck('name', 'id');

        return response()->json([
            'summary' => [
                'overdue_invoices' => BillingInvoice::query()
                    ->whereNull('paid_at')
                    ->whereDate('due_at', '<', now()->toDateString())
                    ->count(),
                'expiring_subscriptions' => Company::query()
                    ->whereHas('subscription', fn ($query) => $query
                        ->where('status', Subscription::STATUS_ACTIVE)
                        ->whereBetween('expires_at', [now(), now()->copy()->addDays(15)]))
                    ->count(),
                'suspended_companies' => Company::query()
                    ->where('status', Company::STATUS_SUSPENDED)
                    ->count(),
                'recent_admin_events' => BusinessAudit::query()
                    ->withoutGlobalScopes()
                    ->where('category', 'admin')
                    ->count(),
            ],
            'overdue_invoices' => $overdueInvoices->map(fn (BillingInvoice $invoice): array => [
                'id' => $invoice->getKey(),
                'status' => $invoice->status,
                'amount_cents' => $invoice->amount_cents,
                'currency' => $invoice->currency,
                'due_at' => $invoice->due_at?->toIso8601String(),
                'invoice_url' => $invoice->invoice_url,
                'company' => [
                    'id' => $invoice->company?->getKey(),
                    'name' => $invoice->company?->name,
                    'document' => $invoice->company?->document,
                ],
                'plan' => [
                    'id' => $invoice->subscription?->plan?->getKey(),
                    'name' => $invoice->subscription?->plan?->name,
                ],
            ])->all(),
            'expiring_subscriptions' => $expiringSubscriptions->map(fn (Company $company): array => [
                'company' => [
                    'id' => $company->getKey(),
                    'name' => $company->name,
                    'document' => $company->document,
                    'status' => $company->status,
                ],
                'subscription' => [
                    'id' => $company->subscription?->getKey(),
                    'status' => $company->subscription?->status,
                    'expires_at' => $company->subscription?->expires_at?->toIso8601String(),
                    'days_until_expiration' => $company->subscription?->daysUntilExpiration(),
                    'plan' => [
                        'id' => $company->subscription?->plan?->getKey(),
                        'name' => $company->subscription?->plan?->name,
                    ],
                ],
            ])->all(),
            'suspended_companies' => $suspendedCompanies->map(fn (Company $company): array => [
                'id' => $company->getKey(),
                'name' => $company->name,
                'document' => $company->document,
                'status' => $company->status,
                'subscription' => [
                    'id' => $company->subscription?->getKey(),
                    'status' => $company->subscription?->status,
                    'plan' => [
                        'id' => $company->subscription?->plan?->getKey(),
                        'name' => $company->subscription?->plan?->name,
                    ],
                ],
            ])->all(),
            'recent_admin_events' => $adminAudits->map(function (BusinessAudit $audit) use ($superAdminNames): array {
                $context = is_array($audit->context) ? $audit->context : [];
                $actorUserId = $context['actor_user_id'] ?? null;
                $isSuperAdminAction = (bool) ($context['actor_super_admin'] ?? false);

                return [
                    'id' => $audit->getKey(),
                    'company_id' => $audit->company_id,
                    'company_name' => $audit->company?->name,
                    'actor_name' => $audit->actorMembership?->user?->name
                        ?? ($actorUserId ? $superAdminNames->get((int) $actorUserId) : null),
                    'actor_type' => $isSuperAdminAction ? 'super_admin' : 'tenant_membership',
                    'event_type' => $audit->event_type,
                    'occurred_at' => $audit->occurred_at?->toIso8601String(),
                ];
            })->all(),
        ]);
    }
}
