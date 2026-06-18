<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Auditing\Actions\RecordBusinessAudit;
use App\Domains\Billing\Actions\SerializeSubscription;
use App\Domains\Companies\Models\Company;
use App\Domains\Companies\Models\CompanyOperationalSetting;
use App\Domains\Companies\Support\FrontendTenantUrl;
use App\Domains\ServiceOrders\Models\CompanyServiceOrderCounter;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AdminCompanyController extends Controller
{
    public function index(Request $request, SerializeSubscription $serializeSubscription): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'string', 'max:120'],
            'plan_id' => ['sometimes', 'integer', 'exists:plans,id'],
        ]);

        $companies = Company::query()
            ->withTrashed()
            ->with(['subscription.plan'])
            ->withCount(['memberships', 'serviceOrders'])
            ->when(
                filled($filters['q'] ?? null),
                fn ($query) => $query->where(function ($inner) use ($filters): void {
                    $term = trim((string) $filters['q']);

                    $inner->where('name', 'like', '%'.$term.'%')
                        ->orWhere('document', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%');
                })
            )
            ->when(
                isset($filters['plan_id']),
                fn ($query) => $query->whereHas('subscription', fn ($subscriptionQuery) => $subscriptionQuery->where('plan_id', $filters['plan_id']))
            )
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $companies->map(fn (Company $company): array => $this->serializeCompanyListItem($company, $serializeSubscription))->all(),
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
                        ->where('status', 'active')
                        ->whereBetween('expires_at', [now(), now()->copy()->addDays(15)]))
                    ->count(),
            ],
        ]);
    }

    public function show(
        Company $company,
        SerializeSubscription $serializeSubscription,
        FrontendTenantUrl $frontendTenantUrl,
    ): JsonResponse
    {
        $company->load(['subscription.plan'])->loadCount(['memberships', 'serviceOrders']);

        return response()->json([
            'data' => array_merge(
                $this->serializeCompanyListItem($company, $serializeSubscription),
                [
                    'trade_name' => $company->trade_name,
                    'phone' => $company->phone,
                    'website' => $company->website,
                    'address_line_1' => $company->address_line_1,
                    'address_line_2' => $company->address_line_2,
                    'district' => $company->district,
                    'city' => $company->city,
                    'state' => $company->state,
                    'postal_code' => $company->postal_code,
                    'passwordless_code_enabled' => $company->passwordless_code_enabled,
                    'magic_link_enabled' => $company->magic_link_enabled,
                    'tenant_url' => $frontendTenantUrl->base($company),
                    'deleted_at' => $company->deleted_at?->toIso8601String(),
                    'recent_invoices' => $company->subscription
                        ? $company->subscription->billingInvoices()
                            ->latest('id')
                            ->limit(5)
                            ->get()
                            ->map(fn ($invoice): array => [
                                'id' => $invoice->getKey(),
                                'status' => $invoice->status,
                                'amount_cents' => $invoice->amount_cents,
                                'currency' => $invoice->currency,
                                'due_at' => $invoice->due_at?->toIso8601String(),
                                'paid_at' => $invoice->paid_at?->toIso8601String(),
                                'invoice_url' => $invoice->invoice_url,
                                'pdf_url' => $invoice->pdf_url,
                            ])->all()
                        : [],
                ],
            ),
        ]);
    }

    public function store(Request $request, RecordBusinessAudit $recordBusinessAudit): JsonResponse
    {
        $data = $this->validateCompanyPayload($request);

        $company = DB::transaction(function () use ($data): Company {
            $company = Company::query()->create($data);

            CompanyOperationalSetting::query()->create([
                'company_id' => $company->getKey(),
                ...CompanyOperationalSetting::defaults(),
            ]);

            CompanyServiceOrderCounter::query()->create([
                'company_id' => $company->getKey(),
                'next_draft_number' => 1,
                'next_service_order_number' => 1,
            ]);

            return $company->fresh(['subscription.plan'])->loadCount(['memberships', 'serviceOrders']);
        });

        $recordBusinessAudit->handle(
            companyId: $company->getKey(),
            category: 'admin',
            eventType: 'company.created',
            auditable: $company,
            actorMembership: null,
            context: [
                'actor_user_id' => $request->user()?->getKey(),
                'actor_super_admin' => true,
            ],
            beforeValues: null,
            afterValues: $this->serializeCompanyLifecycleSnapshot($company),
        );

        return response()->json([
            'data' => $this->serializeCompanyListItem($company, app(SerializeSubscription::class)),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, Company $company, RecordBusinessAudit $recordBusinessAudit): JsonResponse
    {
        $data = $this->validateCompanyPayload($request, $company);
        $before = $this->serializeCompanyLifecycleSnapshot($company);

        $company->fill($data)->save();

        $fresh = $company->fresh(['subscription.plan'])->loadCount(['memberships', 'serviceOrders']);

        $recordBusinessAudit->handle(
            companyId: $company->getKey(),
            category: 'admin',
            eventType: 'company.updated',
            auditable: $company,
            actorMembership: null,
            context: [
                'actor_user_id' => $request->user()?->getKey(),
                'actor_super_admin' => true,
            ],
            beforeValues: $before,
            afterValues: $this->serializeCompanyLifecycleSnapshot($fresh),
        );

        return response()->json([
            'data' => $this->serializeCompanyListItem($fresh, app(SerializeSubscription::class)),
        ]);
    }

    public function suspend(Request $request, Company $company, RecordBusinessAudit $recordBusinessAudit): JsonResponse
    {
        abort_if($company->trashed(), Response::HTTP_NOT_FOUND);

        $before = $this->serializeLifecycle($company);

        $company->forceFill([
            'status' => Company::STATUS_SUSPENDED,
            'is_active' => false,
        ])->save();

        $recordBusinessAudit->handle(
            companyId: $company->getKey(),
            category: 'admin',
            eventType: 'company.suspended',
            auditable: $company,
            actorMembership: null,
            context: [
                'actor_user_id' => $request->user()?->getKey(),
                'actor_super_admin' => true,
            ],
            beforeValues: $before,
            afterValues: $this->serializeLifecycle($company),
        );

        return response()->json([
            'data' => $this->serializeCompanyListItem(
                $company->fresh(['subscription.plan'])->loadCount(['memberships', 'serviceOrders']),
                app(SerializeSubscription::class),
            ),
        ]);
    }

    public function activate(Request $request, Company $company, RecordBusinessAudit $recordBusinessAudit): JsonResponse
    {
        abort_if($company->trashed(), Response::HTTP_NOT_FOUND);

        $before = $this->serializeLifecycle($company);

        $company->forceFill([
            'status' => Company::STATUS_ACTIVE,
            'is_active' => true,
        ])->save();

        $recordBusinessAudit->handle(
            companyId: $company->getKey(),
            category: 'admin',
            eventType: 'company.activated',
            auditable: $company,
            actorMembership: null,
            context: [
                'actor_user_id' => $request->user()?->getKey(),
                'actor_super_admin' => true,
            ],
            beforeValues: $before,
            afterValues: $this->serializeLifecycle($company),
        );

        return response()->json([
            'data' => $this->serializeCompanyListItem(
                $company->fresh(['subscription.plan'])->loadCount(['memberships', 'serviceOrders']),
                app(SerializeSubscription::class),
            ),
        ]);
    }

    public function destroy(Request $request, Company $company, RecordBusinessAudit $recordBusinessAudit): JsonResponse
    {
        $data = $request->validate([
            'confirmation' => ['required', 'string'],
        ]);

        if ($data['confirmation'] !== $company->name) {
            return response()->json([
                'message' => 'The confirmation value must exactly match the company name.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $before = $this->serializeLifecycle($company);

        $company->forceFill([
            'status' => Company::STATUS_CANCELLED,
            'is_active' => false,
        ])->save();

        $company->delete();

        $recordBusinessAudit->handle(
            companyId: $company->getKey(),
            category: 'admin',
            eventType: 'company.deleted',
            auditable: $company,
            actorMembership: null,
            context: [
                'actor_user_id' => $request->user()?->getKey(),
                'actor_super_admin' => true,
            ],
            beforeValues: $before,
            afterValues: $this->serializeLifecycle($company->fresh()),
        );

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    protected function serializeCompanyListItem(Company $company, SerializeSubscription $serializeSubscription): array
    {
        return [
            'id' => $company->getKey(),
            'uuid' => $company->uuid,
            'name' => $company->name,
            'slug' => $company->slug,
            'document' => $company->document,
            'email' => $company->email,
            'created_at' => $company->created_at?->toIso8601String(),
            'status' => $company->status,
            'is_active' => $company->is_active,
            'memberships_count' => $company->memberships_count ?? 0,
            'service_orders_count' => $company->service_orders_count ?? 0,
            'subscription' => $serializeSubscription->handle($company->subscription),
        ];
    }

    protected function serializeLifecycle(?Company $company): array
    {
        return [
            'status' => $company?->status,
            'is_active' => $company?->is_active,
            'deleted_at' => $company?->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateCompanyPayload(Request $request, ?Company $company = null): array
    {
        $companyId = $company?->getKey();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:2'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'string', 'in:active,suspended,cancelled'],
            'is_active' => ['required', 'boolean'],
            'passwordless_code_enabled' => ['required', 'boolean'],
            'magic_link_enabled' => ['required', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeCompanyLifecycleSnapshot(?Company $company): array
    {
        return [
            'name' => $company?->name,
            'trade_name' => $company?->trade_name,
            'document' => $company?->document,
            'email' => $company?->email,
            'phone' => $company?->phone,
            'website' => $company?->website,
            'address_line_1' => $company?->address_line_1,
            'address_line_2' => $company?->address_line_2,
            'district' => $company?->district,
            'city' => $company?->city,
            'state' => $company?->state,
            'postal_code' => $company?->postal_code,
            'status' => $company?->status,
            'is_active' => $company?->is_active,
            'passwordless_code_enabled' => $company?->passwordless_code_enabled,
            'magic_link_enabled' => $company?->magic_link_enabled,
            'deleted_at' => $company?->deleted_at?->toIso8601String(),
        ];
    }
}
