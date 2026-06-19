<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auditing\Actions\RecordBusinessAudit;
use App\Domains\Billing\Actions\ResolveCurrentSubscription;
use App\Domains\Billing\Actions\SerializeSubscription;
use App\Domains\Notifications\Actions\QueueCriticalServiceOrderNotification;
use App\Domains\ServiceOrders\Actions\CreateDraftServiceOrder;
use App\Domains\ServiceOrders\Actions\OpenDraftServiceOrder;
use App\Domains\ServiceOrders\Actions\RecordServiceOrderDiagnosis;
use App\Domains\ServiceOrders\Actions\RecordServiceOrderTimelineEvent;
use App\Domains\ServiceOrders\Actions\ResolveDiagnosableServiceOrder;
use App\Domains\ServiceOrders\Actions\ReserveDraftReference;
use App\Domains\ServiceOrders\Actions\SerializeServiceOrder;
use App\Domains\ServiceOrders\Actions\UpdateDraftServiceOrder;
use App\Domains\ServiceOrders\Actions\ValidateDraftServiceOrderPayload;
use App\Domains\ServiceOrders\Actions\VisibleServiceOrders;
use App\Domains\ServiceOrders\Models\QuoteRevision;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderPrintSnapshot;
use App\Domains\ServiceOrders\Models\ServiceOrderTimelineEvent;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Domains\Auth\Models\Membership;
use Symfony\Component\HttpFoundation\Response;

class ServiceOrderController extends Controller
{
    /**
     * Create a draft intake record with a temporary visible reference.
     *
     * @group Service Orders
     */
    public function storeDraft(
        Request $request,
        ReserveDraftReference $reserveDraftReference,
        ValidateDraftServiceOrderPayload $validateDraftServiceOrderPayload,
        CreateDraftServiceOrder $createDraftServiceOrder,
        SerializeServiceOrder $serializeServiceOrder,
        ResolveCurrentSubscription $resolveCurrentSubscription,
        SerializeSubscription $serializeSubscription,
    ): JsonResponse
    {
        $this->ensurePlanAllowsNewServiceOrder($resolveCurrentSubscription, $serializeSubscription);
        $payload = $validateDraftServiceOrderPayload->handle($request, true);
        $serviceOrder = $createDraftServiceOrder->handle($payload, $reserveDraftReference);

        return response()->json([
            'data' => $serializeServiceOrder->handle($serviceOrder),
        ], Response::HTTP_CREATED);
    }

    /**
     * Update an existing draft intake record so staff can resume and complete it later.
     *
     * @group Service Orders
     */
    public function updateDraft(
        Request $request,
        ServiceOrder $serviceOrder,
        ValidateDraftServiceOrderPayload $validateDraftServiceOrderPayload,
        UpdateDraftServiceOrder $updateDraftServiceOrder,
        SerializeServiceOrder $serializeServiceOrder,
    ): JsonResponse
    {
        $this->ensureServiceOrderBelongsToCurrentCompany($serviceOrder);

        if ($serviceOrder->status !== ServiceOrder::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only draft intake records can be updated through this endpoint.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $payload = $validateDraftServiceOrderPayload->handle($request);
        $serviceOrder = $updateDraftServiceOrder->handle($serviceOrder, $request, $payload);

        return response()->json([
            'data' => $serializeServiceOrder->handle($serviceOrder),
        ]);
    }

    /**
     * Formally open a service order once minimum intake data has been completed.
     *
     * @group Service Orders
     */
    public function openDraft(
        ServiceOrder $serviceOrder,
        OpenDraftServiceOrder $openDraftServiceOrder,
        SerializeServiceOrder $serializeServiceOrder,
        QueueCriticalServiceOrderNotification $queueCriticalServiceOrderNotification,
    ): JsonResponse
    {
        $this->ensureServiceOrderBelongsToCurrentCompany($serviceOrder);
        $this->validateOpenableDraft($serviceOrder);

        $opened = $openDraftServiceOrder->handle($serviceOrder);

        $fresh = $opened->fresh(['customerContact', 'company']);
        $queueCriticalServiceOrderNotification->handle(
            $fresh,
            'service_order_opened',
            [
                'service_order_reference' => $fresh->reference,
                'status' => $fresh->status,
            ],
        );

        return response()->json([
            'data' => $serializeServiceOrder->handle($opened),
        ]);
    }

    /**
     * Record or update structured diagnosis on a visible service order.
     *
     * @group Service Orders
     */
    public function updateDiagnosis(
        Request $request,
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
        ResolveDiagnosableServiceOrder $resolveDiagnosableServiceOrder,
        RecordServiceOrderDiagnosis $recordServiceOrderDiagnosis,
        SerializeServiceOrder $serializeServiceOrder,
    ): JsonResponse {
        $diagnosable = $resolveDiagnosableServiceOrder->handle($serviceOrder, $visibleServiceOrders);
        $membership = $resolveDiagnosableServiceOrder->membership();

        $payload = $request->validate([
            'internal_notes' => ['required', 'string'],
            'customer_summary' => ['required', 'string'],
        ]);

        $diagnosable = $recordServiceOrderDiagnosis->handle(
            $diagnosable,
            $payload,
            $membership,
            $recordTimelineEvent,
        );

        return response()->json([
            'data' => $serializeServiceOrder->handle($diagnosable),
        ]);
    }

    /**
     * Return the internal chronological timeline for a visible service order.
     *
     * @group Service Orders
     */
    public function timeline(ServiceOrder $serviceOrder, VisibleServiceOrders $visibleServiceOrders): JsonResponse
    {
        $membership = currentMembership();

        if ($membership === null) {
            return response()->json(['message' => 'Missing active membership context.'], Response::HTTP_FORBIDDEN);
        }

        $visible = $visibleServiceOrders->queryFor($membership)
            ->whereKey($serviceOrder->getKey())
            ->first();

        abort_if($visible === null, Response::HTTP_NOT_FOUND);

        $visible->load(['timelineEvents.actorMembership']);

        return response()->json([
            'data' => $visible->timelineEvents->map(fn (ServiceOrderTimelineEvent $event): array => [
                'id' => $event->getKey(),
                'type' => $event->type,
                'title' => $event->title,
                'visibility' => $event->visibility,
                'payload' => $event->payload,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
                'actor_membership_id' => $event->actor_membership_id,
            ])->all(),
        ]);
    }

    /**
     * Return a printable payload for one formally opened service order.
     *
     * @group Service Orders
     */
    public function printable(
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
        RecordBusinessAudit $recordBusinessAudit,
    ): JsonResponse
    {
        $membership = currentMembership();

        if ($membership === null) {
            return response()->json(['message' => 'Missing active membership context.'], Response::HTTP_FORBIDDEN);
        }

        $visible = $visibleServiceOrders->queryFor($membership)
            ->whereKey($serviceOrder->getKey())
            ->first();

        abort_if($visible === null, Response::HTTP_NOT_FOUND);

        if ($visible->status === ServiceOrder::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only formally opened service orders can expose a printable version.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $visible->load(['company', 'customer', 'customerContact', 'equipment', 'printSnapshot.firstIssuedByMembership.user', 'printSnapshot.lastIssuedByMembership.user']);

        $snapshot = DB::transaction(function () use ($visible, $membership, $recordTimelineEvent, $recordBusinessAudit): ServiceOrderPrintSnapshot {
            $existingSnapshot = $visible->printSnapshot()->withoutGlobalScopes()->first();

            if ($existingSnapshot === null) {
                $payload = $this->serializePrintableServiceOrder($visible);
                $issuedAt = now();

                $snapshot = ServiceOrderPrintSnapshot::query()->withoutGlobalScopes()->create([
                    'company_id' => $visible->company_id,
                    'service_order_id' => $visible->getKey(),
                    'version' => 1,
                    'payload' => $payload,
                    'first_issued_by_membership_id' => $membership?->getKey(),
                    'first_issued_at' => $issuedAt,
                    'last_issued_by_membership_id' => $membership?->getKey(),
                    'last_issued_at' => $issuedAt,
                    'issue_count' => 1,
                ]);

                $recordTimelineEvent->handle(
                    $visible,
                    type: 'printable_snapshot_issued',
                    title: 'Printable service order issued',
                    visibility: ServiceOrderTimelineEvent::VISIBILITY_INTERNAL,
                    actorMembership: $membership,
                    payload: [
                        'snapshot_version' => $snapshot->version,
                        'issue_type' => 'initial_issue',
                        'issue_count' => 1,
                    ],
                );

                $recordBusinessAudit->handle(
                    $visible->company_id,
                    'service_order_document',
                    'printable_snapshot_issued',
                    $visible,
                    $membership,
                    context: [
                        'snapshot_version' => $snapshot->version,
                        'issue_type' => 'initial_issue',
                        'issue_count' => 1,
                    ],
                    afterValues: [
                        'first_issued_at' => $issuedAt->toIso8601String(),
                        'first_issued_by_membership_id' => $membership?->getKey(),
                    ],
                );

                return $snapshot->fresh(['firstIssuedByMembership.user', 'lastIssuedByMembership.user']);
            }

            $issuedAt = now();
            $previousIssueCount = $existingSnapshot->issue_count;
            $previousIssuedAt = $existingSnapshot->last_issued_at?->toIso8601String();
            $previousIssuedBy = $existingSnapshot->last_issued_by_membership_id;
            $issueCount = $previousIssueCount + 1;

            $existingSnapshot->forceFill([
                'last_issued_by_membership_id' => $membership?->getKey(),
                'last_issued_at' => $issuedAt,
                'issue_count' => $issueCount,
            ])->save();

            $recordTimelineEvent->handle(
                $visible,
                type: 'printable_snapshot_reissued',
                title: 'Printable service order reissued',
                visibility: ServiceOrderTimelineEvent::VISIBILITY_INTERNAL,
                actorMembership: $membership,
                payload: [
                    'snapshot_version' => $existingSnapshot->version,
                    'issue_type' => 'reprint',
                    'issue_count' => $issueCount,
                ],
            );

            $recordBusinessAudit->handle(
                $visible->company_id,
                'service_order_document',
                'printable_snapshot_reissued',
                $visible,
                $membership,
                context: [
                    'snapshot_version' => $existingSnapshot->version,
                    'issue_type' => 'reprint',
                    'issue_count' => $issueCount,
                ],
                beforeValues: [
                    'issue_count' => $previousIssueCount,
                    'last_issued_at' => $previousIssuedAt,
                    'last_issued_by_membership_id' => $previousIssuedBy,
                ],
                afterValues: [
                    'issue_count' => $issueCount,
                    'last_issued_at' => $issuedAt->toIso8601String(),
                    'last_issued_by_membership_id' => $membership?->getKey(),
                ],
            );

            return $existingSnapshot->fresh(['firstIssuedByMembership.user', 'lastIssuedByMembership.user']);
        });

        return response()->json([
            'data' => $this->decoratePrintablePayloadWithDocumentMetadata(
                $snapshot->payload,
                $snapshot,
                $membership,
                $snapshot->issue_count === 1 ? 'initial_issue' : 'reprint',
            ),
        ]);
    }

    /**
     * List tenant-scoped service orders visible to the active membership.
     *
     * @group Service Orders
     *
     * Applies the first record-scope rule: technicians only see service orders assigned to them.
     */
    public function index(VisibleServiceOrders $visibleServiceOrders): JsonResponse
    {
        $membership = currentMembership();

        if ($membership === null) {
            return response()->json(['message' => 'Missing active membership context.'], Response::HTTP_FORBIDDEN);
        }

        if (
            ! $membership->hasPermission('service_orders.view_any')
            && ! $membership->hasPermission('service_orders.view_assigned')
        ) {
            return response()->json([
                'message' => 'This membership cannot view service orders.',
            ], Response::HTTP_FORBIDDEN);
        }

        $perPage = min((int) request()->query('per_page', 15), 100);

        $serviceOrders = $this->buildFilteredVisibleQuery(request(), $visibleServiceOrders, $membership)
            ->with(['customer', 'customerContact', 'equipment', 'assignedMembership.user', 'quoteRevisions'])
            ->paginate($perPage);

        return response()->json([
            'data' => collect($serviceOrders->items())->map(fn (ServiceOrder $so): array => app(SerializeServiceOrder::class)->handle($so))->all(),
            'meta' => [
                'total'        => $serviceOrders->total(),
                'per_page'     => $serviceOrders->perPage(),
                'current_page' => $serviceOrders->currentPage(),
                'last_page'    => $serviceOrders->lastPage(),
                'from'         => $serviceOrders->firstItem() ?? 0,
                'to'           => $serviceOrders->lastItem() ?? 0,
            ],
        ]);
    }

    /**
     * Return compact operational queue and reporting aggregates for visible service orders.
     *
     * @group Service Orders
     */
    public function overview(Request $request, VisibleServiceOrders $visibleServiceOrders): JsonResponse
    {
        $membership = currentMembership();

        if ($membership === null) {
            return response()->json(['message' => 'Missing active membership context.'], Response::HTTP_FORBIDDEN);
        }

        if (
            ! $membership->hasPermission('service_orders.view_any')
            && ! $membership->hasPermission('service_orders.view_assigned')
        ) {
            return response()->json([
                'message' => 'This membership cannot view service orders.',
            ], Response::HTTP_FORBIDDEN);
        }

        $query = $this->buildFilteredVisibleQuery($request, $visibleServiceOrders, $membership);
        $serviceOrders = $query
            ->with(['assignedMembership.user', 'quoteRevisions', 'payments'])
            ->get();

        return response()->json([
            'data' => [
                'scope' => [
                    'mode' => $membership->hasPermission('service_orders.view_any') ? 'tenant' : 'assigned',
                    'membership_id' => $membership->getKey(),
                    'membership_name' => $membership->user?->name,
                ],
                'filters' => $request->only(['q', 'queue', 'assigned_membership_id', 'status']),
                'totals' => [
                    'service_orders' => $serviceOrders->count(),
                ],
                'queues' => $this->summarizeOperationalQueues($serviceOrders),
                'attention' => [
                    'unassigned_open' => $serviceOrders
                        ->where('status', ServiceOrder::STATUS_OPEN)
                        ->whereNull('assigned_membership_id')
                        ->count(),
                    'pending_quote' => $serviceOrders
                        ->filter(fn (ServiceOrder $serviceOrder): bool => $serviceOrder->quoteRevisions
                            ->contains(fn (QuoteRevision $quoteRevision): bool => $quoteRevision->status === QuoteRevision::STATUS_PENDING))
                        ->count(),
                    'ready_for_pickup' => $serviceOrders
                        ->where('status', ServiceOrder::STATUS_READY_FOR_PICKUP)
                        ->count(),
                    'warranty_pending_review' => $serviceOrders
                        ->whereNotNull('warranty_origin_service_order_id')
                        ->where('warranty_decision_status', ServiceOrder::WARRANTY_DECISION_PENDING)
                        ->count(),
                ],
                'by_status' => $serviceOrders
                    ->groupBy('status')
                    ->map(fn (Collection $orders): int => $orders->count())
                    ->all(),
                'technician_workload' => $serviceOrders
                    ->filter(fn (ServiceOrder $serviceOrder): bool => $serviceOrder->assignedMembership !== null)
                    ->groupBy('assigned_membership_id')
                    ->map(function (Collection $orders): array {
                        /** @var ServiceOrder $first */
                        $first = $orders->first();

                        return [
                            'assigned_membership_id' => $first->assigned_membership_id,
                            'assigned_membership_name' => $first->assignedMembership?->user?->name,
                            'service_order_count' => $orders->count(),
                        ];
                    })
                    ->sortByDesc('service_order_count')
                    ->values()
                    ->all(),
                'quote_outcomes' => $serviceOrders
                    ->flatMap(fn (ServiceOrder $serviceOrder): Collection => $serviceOrder->quoteRevisions)
                    ->groupBy('status')
                    ->map(fn (Collection $quotes): int => $quotes->count())
                    ->all(),
                'received_amounts' => [
                    'total_received_cents' => (int) $serviceOrders
                        ->flatMap(fn (ServiceOrder $serviceOrder): Collection => $serviceOrder->payments)
                        ->sum('amount_cents'),
                    'by_payment_method' => $serviceOrders
                        ->flatMap(fn (ServiceOrder $serviceOrder): Collection => $serviceOrder->payments)
                        ->groupBy('payment_method')
                        ->map(fn (Collection $payments): int => (int) $payments->sum('amount_cents'))
                        ->all(),
                ],
                'warranty_returns' => [
                    'total' => $serviceOrders->whereNotNull('warranty_origin_service_order_id')->count(),
                    'by_decision_status' => $serviceOrders
                        ->whereNotNull('warranty_origin_service_order_id')
                        ->groupBy(fn (ServiceOrder $serviceOrder): string => $serviceOrder->warranty_decision_status ?? ServiceOrder::WARRANTY_DECISION_PENDING)
                        ->map(fn (Collection $orders): int => $orders->count())
                        ->all(),
                ],
                'financial_kpis' => (function () use ($serviceOrders): array {
                    $thisMonthStart  = now()->startOfMonth();
                    $lastMonthStart  = now()->subMonth()->startOfMonth();
                    $lastMonthEnd    = now()->subMonth()->endOfMonth();
                    $thirtyDaysAgo   = now()->subDays(30);

                    $allPayments = $serviceOrders
                        ->flatMap(fn (ServiceOrder $serviceOrder): Collection => $serviceOrder->payments);

                    // This-month payments: received_at >= start of current month and <= now
                    $thisMonthPayments = $allPayments->filter(
                        fn ($payment): bool => $payment->received_at !== null
                            && $payment->received_at->gte($thisMonthStart)
                            && $payment->received_at->lte(now())
                    );

                    // Last-month payments: received_at within last calendar month
                    $lastMonthPayments = $allPayments->filter(
                        fn ($payment): bool => $payment->received_at !== null
                            && $payment->received_at->gte($lastMonthStart)
                            && $payment->received_at->lte($lastMonthEnd)
                    );

                    // Orders that have at least one payment received this month
                    $thisMonthOrderIds = $thisMonthPayments->pluck('service_order_id')->unique();
                    $thisMonthOrderCount = $thisMonthOrderIds->count();
                    $thisMonthRevenue = (int) $thisMonthPayments->sum('amount_cents');
                    $thisMonthAvgTicket = $thisMonthOrderCount > 0
                        ? (int) round($thisMonthRevenue / $thisMonthOrderCount)
                        : 0;

                    // Orders that have at least one payment received last month
                    $lastMonthOrderIds = $lastMonthPayments->pluck('service_order_id')->unique();
                    $lastMonthOrderCount = $lastMonthOrderIds->count();
                    $lastMonthRevenue = (int) $lastMonthPayments->sum('amount_cents');
                    $lastMonthAvgTicket = $lastMonthOrderCount > 0
                        ? (int) round($lastMonthRevenue / $lastMonthOrderCount)
                        : 0;

                    // Month-over-month revenue change percentage
                    $momRevenueChangePct = $lastMonthRevenue > 0
                        ? round(($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue * 100, 2)
                        : null;

                    // Completion rate for orders opened in the last 30 days
                    $recentOrders = $serviceOrders->filter(
                        fn (ServiceOrder $serviceOrder): bool => $serviceOrder->opened_at !== null
                            && $serviceOrder->opened_at->gte($thirtyDaysAgo)
                    );
                    $recentTotal = $recentOrders->count();
                    $completedStatuses = ['delivered', 'completed', 'no_repair'];
                    $recentCompleted = $recentOrders->filter(
                        fn (ServiceOrder $serviceOrder): bool => in_array($serviceOrder->status, $completedStatuses, true)
                    )->count();
                    $completionRate30d = $recentTotal > 0
                        ? round($recentCompleted / $recentTotal * 100, 2)
                        : null;

                    return [
                        'this_month' => [
                            'revenue_cents'    => $thisMonthRevenue,
                            'order_count'      => $thisMonthOrderCount,
                            'avg_ticket_cents' => $thisMonthAvgTicket,
                        ],
                        'last_month' => [
                            'revenue_cents'    => $lastMonthRevenue,
                            'order_count'      => $lastMonthOrderCount,
                            'avg_ticket_cents' => $lastMonthAvgTicket,
                        ],
                        'mom_revenue_change_pct' => $momRevenueChangePct,
                        'completion_rate_30d'    => $completionRate30d,
                    ];
                })(),
            ],
        ]);
    }

    /**
     * Return a filterable service order report for the visible scope.
     *
     * @group Service Orders
     */
    public function serviceOrderReport(Request $request, VisibleServiceOrders $visibleServiceOrders)
    {
        $membership = currentMembership();

        if ($membership === null) {
            return response()->json(['message' => 'Missing active membership context.'], Response::HTTP_FORBIDDEN);
        }

        if (
            ! $membership->hasPermission('service_orders.view_any')
            && ! $membership->hasPermission('service_orders.view_assigned')
        ) {
            return response()->json([
                'message' => 'This membership cannot view service orders.',
            ], Response::HTTP_FORBIDDEN);
        }

        $perPage = min((int) $request->query('per_page', 15), 100);

        $paginator = $this->buildFilteredVisibleQuery($request, $visibleServiceOrders, $membership)
            ->with(['customer', 'equipment', 'assignedMembership.user', 'quoteRevisions'])
            ->paginate($perPage);

        $rows = collect($paginator->items())->map(fn (ServiceOrder $serviceOrder): array => [
            'id' => $serviceOrder->getKey(),
            'reference' => $serviceOrder->reference,
            'order_number' => $serviceOrder->order_number,
            'status' => $serviceOrder->status,
            'opened_at' => $serviceOrder->opened_at?->toIso8601String(),
            'customer_name' => $serviceOrder->customer?->name,
            'customer_document' => $serviceOrder->customer?->document,
            'equipment_summary' => trim(collect([
                $serviceOrder->equipment?->brand,
                $serviceOrder->equipment?->model,
                $serviceOrder->equipment?->serial_number ? 'S/N '.$serviceOrder->equipment->serial_number : null,
            ])->filter()->implode(' ')),
            'assigned_membership_name' => $serviceOrder->assignedMembership?->user?->name,
            'latest_quote_status' => $serviceOrder->quoteRevisions->sortByDesc('revision_number')->first()?->status,
            'warranty_decision_status' => $serviceOrder->warranty_decision_status,
        ]);

        $format = $request->query('format');

        if ($format === 'csv' || $format === 'print') {
            // Both CSV and print export the full result set — bypass pagination
            $all = $this->buildFilteredVisibleQuery($request, $visibleServiceOrders, $membership)
                ->with(['customer', 'equipment', 'assignedMembership.user', 'quoteRevisions'])
                ->get();
            $allRows = $all->map(fn (ServiceOrder $so): array => [
                'id'                       => $so->getKey(),
                'reference'                => $so->reference,
                'order_number'             => $so->order_number,
                'status'                   => $so->status,
                'opened_at'                => $so->opened_at?->toIso8601String(),
                'customer_name'            => $so->customer?->name,
                'customer_document'        => $so->customer?->document,
                'equipment_summary'        => trim(collect([
                    $so->equipment?->brand,
                    $so->equipment?->model,
                    $so->equipment?->serial_number ? 'S/N '.$so->equipment->serial_number : null,
                ])->filter()->implode(' ')),
                'assigned_membership_name' => $so->assignedMembership?->user?->name,
                'latest_quote_status'      => $so->quoteRevisions->sortByDesc('revision_number')->first()?->status,
                'warranty_decision_status' => $so->warranty_decision_status,
            ]);

            if ($format === 'print') {
                return response()->json(['data' => $allRows->all()]);
            }

            return $this->downloadCsv(
                'relatorio-ordens-servico.csv',
                ['Referencia', 'Numero OS', 'Status', 'Abertura', 'Cliente', 'Documento', 'Equipamento', 'Tecnico', 'Ultimo Orcamento', 'Garantia'],
                $allRows->map(fn (array $row): array => [
                    $row['reference'],
                    $row['order_number'] ?? '',
                    $row['status'],
                    $row['opened_at'] ?? '',
                    $row['customer_name'] ?? '',
                    $row['customer_document'] ?? '',
                    $row['equipment_summary'] ?? '',
                    $row['assigned_membership_name'] ?? '',
                    $row['latest_quote_status'] ?? '',
                    $row['warranty_decision_status'] ?? '',
                ]),
            );
        }

        return response()->json([
            'data' => $rows->all(),
            'meta' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem() ?? 0,
                'to'           => $paginator->lastItem() ?? 0,
            ],
            'filters' => $request->only(['q', 'queue', 'assigned_membership_id', 'status', 'opened_from', 'opened_to']),
        ]);
    }

    /**
     * Return a filterable financial report for visible service orders.
     *
     * @group Service Orders
     */
    public function financialReport(Request $request, VisibleServiceOrders $visibleServiceOrders)
    {
        $membership = currentMembership();

        if ($membership === null) {
            return response()->json(['message' => 'Missing active membership context.'], Response::HTTP_FORBIDDEN);
        }

        if (! $membership->hasPermission('service_orders.finance')) {
            return response()->json([
                'message' => 'This membership cannot view financial reports.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (
            ! $membership->hasPermission('service_orders.view_any')
            && ! $membership->hasPermission('service_orders.view_assigned')
        ) {
            return response()->json([
                'message' => 'This membership cannot view service orders.',
            ], Response::HTTP_FORBIDDEN);
        }

        $filters = $request->validate([
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:50'],
            'balance_state' => ['sometimes', 'nullable', Rule::in(['settled', 'outstanding'])],
            'received_from' => ['sometimes', 'nullable', 'date'],
            'received_to' => ['sometimes', 'nullable', 'date'],
        ]);

        $perPage      = min((int) $request->query('per_page', 15), 100);
        $currentPage  = max(1, (int) $request->query('page', 1));

        $serviceOrders = $this->buildFilteredVisibleQuery($request, $visibleServiceOrders, $membership)
            ->with(['customer', 'assignedMembership.user', 'charges', 'payments'])
            ->get()
            ->filter(function (ServiceOrder $serviceOrder) use ($filters): bool {
                $payments = $serviceOrder->payments;

                if (filled($filters['payment_method'] ?? null) && ! $payments->contains('payment_method', $filters['payment_method'])) {
                    return false;
                }

                if (filled($filters['received_from'] ?? null) || filled($filters['received_to'] ?? null)) {
                    $payments = $payments->filter(function ($payment) use ($filters): bool {
                        $receivedAt = $payment->received_at?->toDateString();

                        if ($receivedAt === null) {
                            return false;
                        }

                        if (filled($filters['received_from'] ?? null) && $receivedAt < $filters['received_from']) {
                            return false;
                        }

                        if (filled($filters['received_to'] ?? null) && $receivedAt > $filters['received_to']) {
                            return false;
                        }

                        return true;
                    });

                    if ($payments->isEmpty()) {
                        return false;
                    }
                }

                $summary = $this->summarizeFinancials($serviceOrder);
                $isOutstanding = $summary['outstanding_balance_cents'] > 0;

                if (($filters['balance_state'] ?? null) === 'outstanding' && ! $isOutstanding) {
                    return false;
                }

                if (($filters['balance_state'] ?? null) === 'settled' && $isOutstanding) {
                    return false;
                }

                return true;
            })
            ->values();

        $rows = $serviceOrders->map(function (ServiceOrder $serviceOrder): array {
            $summary = $this->summarizeFinancials($serviceOrder);

            return [
                'service_order_id' => $serviceOrder->getKey(),
                'reference' => $serviceOrder->reference,
                'order_number' => $serviceOrder->order_number,
                'status' => $serviceOrder->status,
                'customer_name' => $serviceOrder->customer?->name,
                'assigned_membership_name' => $serviceOrder->assignedMembership?->user?->name,
                'total_charged_cents' => $summary['total_charged_cents'],
                'total_paid_cents' => $summary['total_paid_cents'],
                'outstanding_balance_cents' => $summary['outstanding_balance_cents'],
                'payment_methods' => $serviceOrder->payments
                    ->pluck('payment_method')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
                'last_payment_at' => $serviceOrder->payments
                    ->sortByDesc(fn ($payment) => $payment->received_at?->getTimestamp() ?? 0)
                    ->first()?->received_at?->toIso8601String(),
            ];
        });

        $format = $request->query('format');

        if ($format === 'csv' || $format === 'print') {
            $totals = [
                'total_charged_cents'       => (int) $rows->sum('total_charged_cents'),
                'total_paid_cents'          => (int) $rows->sum('total_paid_cents'),
                'outstanding_balance_cents' => (int) $rows->sum('outstanding_balance_cents'),
            ];

            if ($format === 'print') {
                return response()->json(['data' => $rows->all(), 'totals' => $totals]);
            }

            return $this->downloadCsv(
                'relatorio-financeiro.csv',
                ['Referencia', 'Numero OS', 'Status', 'Cliente', 'Tecnico', 'Cobrado', 'Pago', 'Saldo', 'Meios de pagamento', 'Ultimo pagamento'],
                $rows->map(fn (array $row): array => [
                    $row['reference'],
                    $row['order_number'] ?? '',
                    $row['status'],
                    $row['customer_name'] ?? '',
                    $row['assigned_membership_name'] ?? '',
                    $row['total_charged_cents'],
                    $row['total_paid_cents'],
                    $row['outstanding_balance_cents'],
                    implode(', ', $row['payment_methods']),
                    $row['last_payment_at'] ?? '',
                ]),
            );
        }

        // Totals computed from full filtered set (before page slice)
        $totals = [
            'total_charged_cents'      => (int) $rows->sum('total_charged_cents'),
            'total_paid_cents'         => (int) $rows->sum('total_paid_cents'),
            'outstanding_balance_cents' => (int) $rows->sum('outstanding_balance_cents'),
        ];

        $total     = $rows->count();
        $lastPage  = max(1, (int) ceil($total / $perPage));
        $currentPage = min($currentPage, $lastPage);
        $offset    = ($currentPage - 1) * $perPage;
        $pageRows  = $rows->slice($offset, $perPage)->values();

        return response()->json([
            'data' => $pageRows->all(),
            'meta' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $currentPage,
                'last_page'    => $lastPage,
                'from'         => $total > 0 ? $offset + 1 : 0,
                'to'           => min($offset + $perPage, $total),
                'totals'       => $totals,
            ],
            'filters' => $request->only([
                'q',
                'assigned_membership_id',
                'status',
                'opened_from',
                'opened_to',
                'payment_method',
                'balance_state',
                'received_from',
                'received_to',
            ]),
        ]);
    }

    /**
     * @return array<string, int>
     */
    protected function summarizeOperationalQueues(Collection $serviceOrders): array
    {
        return [
            'drafts' => $serviceOrders->where('status', ServiceOrder::STATUS_DRAFT)->count(),
            'open' => $serviceOrders->where('status', ServiceOrder::STATUS_OPEN)->count(),
            'pending_quote' => $serviceOrders
                ->filter(fn (ServiceOrder $serviceOrder): bool => $serviceOrder->quoteRevisions
                    ->contains(fn (QuoteRevision $quoteRevision): bool => $quoteRevision->status === QuoteRevision::STATUS_PENDING))
                ->count(),
            'in_progress' => $serviceOrders->where('status', ServiceOrder::STATUS_IN_PROGRESS)->count(),
            'ready_for_pickup' => $serviceOrders->where('status', ServiceOrder::STATUS_READY_FOR_PICKUP)->count(),
            'terminal' => $serviceOrders
                ->whereIn('status', [
                    ServiceOrder::STATUS_DELIVERED,
                    ServiceOrder::STATUS_CANCELLED,
                    ServiceOrder::STATUS_NO_REPAIR,
                ])
                ->count(),
            'warranty_returns' => $serviceOrders->whereNotNull('warranty_origin_service_order_id')->count(),
        ];
    }

    /**
     * Return one visible service order.
     *
     * @group Service Orders
     *
     * Returns 404 when the service order exists in the tenant but is outside the membership record scope.
     */
    public function show(ServiceOrder $serviceOrder, VisibleServiceOrders $visibleServiceOrders): JsonResponse
    {
        $membership = currentMembership();

        if ($membership === null) {
            return response()->json(['message' => 'Missing active membership context.'], Response::HTTP_FORBIDDEN);
        }

        $visible = $visibleServiceOrders->queryFor($membership)
            ->whereKey($serviceOrder->getKey())
            ->first();

        abort_if($visible === null, Response::HTTP_NOT_FOUND);

        $visible->load(['customer', 'customerContact', 'equipment', 'assignedMembership', 'executionEntries']);

        $data = app(SerializeServiceOrder::class)->handle($visible);
        $data['execution_started_at'] = $visible->execution_started_at?->toIso8601String();
        $data['final_test_results'] = $visible->final_test_results;
        $data['execution_entries'] = $visible->executionEntries->map(fn ($e) => [
            'id' => $e->getKey(),
            'entry_type' => $e->entry_type,
            'description' => $e->description,
            'quantity' => $e->quantity,
            'notes' => $e->notes,
        ])->all();

        return response()->json(['data' => $data]);
    }

    protected function validateOpenableDraft(ServiceOrder $serviceOrder): void
    {
        if ($serviceOrder->status !== ServiceOrder::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft intake records can be opened.',
            ]);
        }

        $validator = validator($serviceOrder->toArray(), [
            'customer_id' => ['required', 'integer'],
            'customer_contact_id' => ['required', 'integer'],
            'equipment_id' => ['required', 'integer'],
            'intake_checklist_template_id' => ['required', 'integer'],
            'equipment_description' => ['required', 'string'],
            'reported_problem' => ['required', 'string'],
            'intake_checklist' => ['required', 'array', 'min:1'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }

    protected function ensurePlanAllowsNewServiceOrder(
        ResolveCurrentSubscription $resolveCurrentSubscription,
        SerializeSubscription $serializeSubscription,
    ): void {
        $company = currentCompany();

        abort_if($company === null, Response::HTTP_FORBIDDEN, 'Missing active company context.');

        $subscription = $resolveCurrentSubscription->handle($company);

        abort_if($subscription === null, Response::HTTP_PAYMENT_REQUIRED, 'A empresa não possui assinatura ativa configurada.');

        $limit = $subscription->plan?->max_service_orders_per_month;

        if ($limit === null) {
            return;
        }

        $count = ServiceOrder::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->getKey())
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        if ($count >= $limit) {
            abort(response()->json([
                'message' => 'O limite mensal de ordens de serviço do plano foi atingido.',
                'subscription' => $serializeSubscription->handle($subscription),
            ], Response::HTTP_PAYMENT_REQUIRED));
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializePrintableServiceOrder(ServiceOrder $serviceOrder): array
    {
        $equipmentSnapshot = collect($serviceOrder->equipment_snapshot ?? []);
        $checklist = collect($serviceOrder->intake_checklist ?? [])
            ->map(function (mixed $item): array {
                $normalized = match (true) {
                    $item instanceof \ArrayObject => $item->getArrayCopy(),
                    is_array($item) => $item,
                    is_object($item) => (array) $item,
                    default => [],
                };

                $label = isset($normalized['label']) && is_string($normalized['label']) && trim($normalized['label']) !== ''
                    ? trim($normalized['label'])
                    : 'Item sem descricao';

                $notes = isset($normalized['notes']) && is_string($normalized['notes']) && trim($normalized['notes']) !== ''
                    ? trim($normalized['notes'])
                    : null;

                return [
                    'label' => $label,
                    'checked' => (bool) ($normalized['checked'] ?? false),
                    'notes' => $notes,
                ];
            })
            ->values()
            ->all();

        $observations = collect($checklist)
            ->pluck('notes')
            ->filter(fn (mixed $note): bool => is_string($note) && trim($note) !== '')
            ->map(fn (string $note): string => 'Checklist: '.trim($note));

        if (is_string($equipmentSnapshot->get('accessories')) && trim((string) $equipmentSnapshot->get('accessories')) !== '') {
            $observations->prepend('Acessorios informados: '.trim((string) $equipmentSnapshot->get('accessories')));
        }

        if (is_string($equipmentSnapshot->get('notes')) && trim((string) $equipmentSnapshot->get('notes')) !== '') {
            $observations->push('Observacoes do equipamento: '.trim((string) $equipmentSnapshot->get('notes')));
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'company' => [
                'name' => $serviceOrder->company?->name,
                'document' => $serviceOrder->company?->document,
                'email' => $serviceOrder->company?->email,
                'phone' => $serviceOrder->company?->phone,
            ],
            'service_order' => [
                'id' => $serviceOrder->getKey(),
                'reference' => $serviceOrder->reference,
                'order_number' => $serviceOrder->order_number,
                'status' => $serviceOrder->status,
                'opened_at' => $serviceOrder->opened_at?->toIso8601String(),
                'title' => $serviceOrder->title,
                'reported_problem' => $serviceOrder->reported_problem,
                'equipment_description' => $serviceOrder->equipment_description ?: $equipmentSnapshot->get('captured_description'),
                'customer' => [
                    'name' => $serviceOrder->customer?->name,
                    'document' => $serviceOrder->customer?->document,
                    'contact_name' => $serviceOrder->customerContact?->name,
                    'contact_phone' => $serviceOrder->customerContact?->phone,
                ],
                'equipment' => [
                    'category' => $equipmentSnapshot->get('category') ?: $serviceOrder->equipment?->category,
                    'brand' => $equipmentSnapshot->get('brand') ?: $serviceOrder->equipment?->brand,
                    'model' => $equipmentSnapshot->get('model') ?: $serviceOrder->equipment?->model,
                    'serial_number' => $equipmentSnapshot->get('serial_number') ?: $serviceOrder->equipment?->serial_number,
                    'accessories' => $equipmentSnapshot->get('accessories'),
                ],
                'intake_checklist' => $checklist,
                'observations' => $observations->values()->all(),
                'signature_blocks' => [
                    ['key' => 'customer', 'label' => 'Assinatura do cliente'],
                    ['key' => 'attendant', 'label' => 'Assinatura do atendente'],
                ],
            ],
            'copies' => [
                ['key' => 'company', 'label' => 'Via da empresa'],
                ['key' => 'customer', 'label' => 'Via do cliente'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function decoratePrintablePayloadWithDocumentMetadata(
        array $payload,
        ServiceOrderPrintSnapshot $snapshot,
        ?Membership $currentMembership,
        string $issueType,
    ): array {
        $payload['document'] = [
            'version' => $snapshot->version,
            'issue_count' => $snapshot->issue_count,
            'issue_type' => $issueType,
            'snapshot_issued_at' => $snapshot->first_issued_at?->toIso8601String(),
            'snapshot_issued_by_membership_id' => $snapshot->first_issued_by_membership_id,
            'snapshot_issued_by_name' => $snapshot->firstIssuedByMembership?->user?->name,
            'issued_at' => $snapshot->last_issued_at?->toIso8601String(),
            'issued_by_membership_id' => $snapshot->last_issued_by_membership_id,
            'issued_by_name' => $snapshot->lastIssuedByMembership?->user?->name ?? $currentMembership?->user?->name,
        ];

        return $payload;
    }

    /**
     * @return array<string, int|string>
     */
    protected function summarizeFinancials(ServiceOrder $serviceOrder): array
    {
        $totalChargedCents = (int) $serviceOrder->charges->sum('amount_cents');
        $totalPaidCents = (int) $serviceOrder->payments->sum('amount_cents');

        return [
            'currency' => (string) ($serviceOrder->charges->first()?->currency ?? $serviceOrder->payments->first()?->currency ?? 'BRL'),
            'total_charged_cents' => $totalChargedCents,
            'total_paid_cents' => $totalPaidCents,
            'outstanding_balance_cents' => $totalChargedCents - $totalPaidCents,
        ];
    }

    protected function buildFilteredVisibleQuery(
        Request $request,
        VisibleServiceOrders $visibleServiceOrders,
        $membership,
    ): Builder {
        $filters = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'queue' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    'drafts',
                    'open',
                    'pending_quote',
                    'in_progress',
                    'ready_for_pickup',
                    'terminal',
                    'warranty_returns',
                ]),
            ],
            'assigned_membership_id' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', 'array'],
            'status.*' => ['string'],
            'opened_from' => ['sometimes', 'nullable', 'date'],
            'opened_to' => ['sometimes', 'nullable', 'date'],
        ]);

        $query = $visibleServiceOrders->queryFor($membership);

        if (filled($filters['assigned_membership_id'] ?? null)) {
            $query->where('assigned_membership_id', $filters['assigned_membership_id']);
        }

        if (($filters['status'] ?? null) !== null) {
            $query->whereIn('status', $filters['status']);
        }

        if (filled($filters['opened_from'] ?? null)) {
            $query->whereDate('opened_at', '>=', $filters['opened_from']);
        }

        if (filled($filters['opened_to'] ?? null)) {
            $query->whereDate('opened_at', '<=', $filters['opened_to']);
        }

        if (filled($filters['queue'] ?? null)) {
            match ($filters['queue']) {
                'drafts' => $query->where('status', ServiceOrder::STATUS_DRAFT),
                'open' => $query->where('status', ServiceOrder::STATUS_OPEN),
                'pending_quote' => $query->whereHas('quoteRevisions', fn (Builder $quoteQuery): Builder => $quoteQuery
                    ->where('status', QuoteRevision::STATUS_PENDING)),
                'in_progress' => $query->where('status', ServiceOrder::STATUS_IN_PROGRESS),
                'ready_for_pickup' => $query->where('status', ServiceOrder::STATUS_READY_FOR_PICKUP),
                'terminal' => $query->whereIn('status', [
                    ServiceOrder::STATUS_DELIVERED,
                    ServiceOrder::STATUS_CANCELLED,
                    ServiceOrder::STATUS_NO_REPAIR,
                ]),
                'warranty_returns' => $query->whereNotNull('warranty_origin_service_order_id'),
            };
        }

        if (filled($filters['q'] ?? null)) {
            $term = trim((string) $filters['q']);
            $normalizedDigits = preg_replace('/\D+/', '', $term) ?: null;
            $useNormalizedPhoneSearch = $normalizedDigits !== null
                && preg_match('/[[:alpha:]]/u', $term) !== 1;

            $query->where(function (Builder $serviceOrderQuery) use ($term, $normalizedDigits, $useNormalizedPhoneSearch): void {
                $serviceOrderQuery
                    ->where('reference', 'like', '%'.$term.'%')
                    ->orWhere('draft_reference', 'like', '%'.$term.'%')
                    ->orWhere('title', 'like', '%'.$term.'%')
                    ->orWhere('reported_problem', 'like', '%'.$term.'%')
                    ->orWhere('order_number', is_numeric($term) ? (int) $term : -1)
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($term): void {
                        $customerQuery
                            ->where('name', 'like', '%'.$term.'%')
                            ->orWhere('trade_name', 'like', '%'.$term.'%')
                            ->orWhere('document', 'like', '%'.$term.'%');
                    })
                    ->orWhereHas('customerContact', function (Builder $contactQuery) use ($term, $normalizedDigits, $useNormalizedPhoneSearch): void {
                        $contactQuery
                            ->where('name', 'like', '%'.$term.'%')
                            ->orWhere('phone', 'like', '%'.$term.'%')
                            ->orWhere('email', 'like', '%'.$term.'%');

                        if ($useNormalizedPhoneSearch && $normalizedDigits !== null) {
                            $contactQuery->orWhere('normalized_phone', 'like', '%'.$normalizedDigits.'%');
                        }
                    })
                    ->orWhereHas('equipment', function (Builder $equipmentQuery) use ($term): void {
                        $equipmentQuery
                            ->where('serial_number', 'like', '%'.$term.'%')
                            ->orWhere('brand', 'like', '%'.$term.'%')
                            ->orWhere('model', 'like', '%'.$term.'%');
                    });
            });
        }

        return $query;
    }

    /**
     * Return monthly aggregated counts for opened/completed OS and received payments.
     *
     * @group Service Orders
     */
    public function trendsReport(Request $request, VisibleServiceOrders $visibleServiceOrders): JsonResponse
    {
        $membership = currentMembership();

        if ($membership === null) {
            return response()->json(['message' => 'Missing active membership context.'], Response::HTTP_FORBIDDEN);
        }

        if (
            ! $membership->hasPermission('service_orders.view_any')
            && ! $membership->hasPermission('service_orders.view_assigned')
        ) {
            return response()->json([
                'message' => 'This membership cannot view service orders.',
            ], Response::HTTP_FORBIDDEN);
        }

        $months = min(max((int) $request->query('months', 6), 1), 12);
        $companyId = currentCompany()->getKey();
        $from = now()->startOfMonth()->subMonths($months - 1);

        // Use strftime (SQLite) or DATE_FORMAT (MySQL/MariaDB) depending on driver
        $isSqlite = DB::getDriverName() === 'sqlite';
        $monthFmt = fn (string $col): string => $isSqlite
            ? "strftime('%Y-%m', {$col})"
            : "DATE_FORMAT({$col}, '%Y-%m')";

        $opened = DB::table('service_orders')
            ->where('company_id', $companyId)
            ->where('opened_at', '>=', $from)
            ->whereNull('deleted_at')
            ->select(DB::raw($monthFmt('opened_at') . ' as month'), DB::raw('COUNT(*) as count'))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $completed = DB::table('service_orders')
            ->where('company_id', $companyId)
            ->whereIn('status', [ServiceOrder::STATUS_DELIVERED, 'completed'])
            ->where('updated_at', '>=', $from)
            ->whereNull('deleted_at')
            ->select(DB::raw($monthFmt('updated_at') . ' as month'), DB::raw('COUNT(*) as count'))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $received = DB::table('service_order_payments')
            ->join('service_orders', 'service_orders.id', '=', 'service_order_payments.service_order_id')
            ->where('service_orders.company_id', $companyId)
            ->where('service_order_payments.received_at', '>=', $from)
            ->select(DB::raw($monthFmt('service_order_payments.received_at') . ' as month'), DB::raw('SUM(service_order_payments.amount_cents) as total_cents'))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $monthList = collect();
        for ($i = $months - 1; $i >= 0; $i--) {
            $m = now()->subMonths($i)->format('Y-m');
            $label = now()->subMonths($i)->locale('pt_BR')->isoFormat('MMM/YY');
            $monthList->push([
                'month' => $m,
                'label' => $label,
                'opened' => (int) ($opened[$m]->count ?? 0),
                'completed' => (int) ($completed[$m]->count ?? 0),
                'received_cents' => (int) ($received[$m]->total_cents ?? 0),
            ]);
        }

        return response()->json([
            'data' => [
                'months' => $monthList->values()->all(),
            ],
        ]);
    }

    /**
     * Return per-technician performance stats for visible service orders.
     *
     * @group Service Orders
     */
    public function technicianReport(Request $request, VisibleServiceOrders $visibleServiceOrders)
    {
        $membership = currentMembership();

        if ($membership === null) {
            return response()->json(['message' => 'Missing active membership context.'], Response::HTTP_FORBIDDEN);
        }

        if (
            ! $membership->hasPermission('service_orders.view_any')
            && ! $membership->hasPermission('service_orders.view_assigned')
        ) {
            return response()->json([
                'message' => 'This membership cannot view service orders.',
            ], Response::HTTP_FORBIDDEN);
        }

        $serviceOrders = $this->buildFilteredVisibleQuery($request, $visibleServiceOrders, $membership)
            ->with(['assignedMembership.user', 'payments'])
            ->get();

        $grouped = $serviceOrders->groupBy(fn (ServiceOrder $so): string => (string) ($so->assigned_membership_id ?? 'unassigned'));

        $rows = $grouped->map(function (Collection $orders, string $key): array {
            /** @var ServiceOrder $first */
            $first = $orders->first();

            $completedStatuses = [ServiceOrder::STATUS_DELIVERED, 'completed'];

            return [
                'id' => $key === 'unassigned' ? null : (int) $key,
                'name' => $key === 'unassigned' ? 'Não atribuído' : ($first->assignedMembership?->user?->name ?? 'Desconhecido'),
                'total' => $orders->count(),
                'in_progress' => $orders->where('status', ServiceOrder::STATUS_IN_PROGRESS)->count(),
                'completed' => $orders->whereIn('status', $completedStatuses)->count(),
                'cancelled' => $orders->where('status', ServiceOrder::STATUS_CANCELLED)->count(),
                'no_repair' => $orders->where('status', ServiceOrder::STATUS_NO_REPAIR)->count(),
                'pending' => $orders->whereIn('status', [
                    ServiceOrder::STATUS_OPEN,
                    ServiceOrder::STATUS_READY_FOR_PICKUP,
                    'awaiting_quote',
                ])->count(),
                'total_received_cents' => (int) $orders
                    ->flatMap(fn (ServiceOrder $so): Collection => $so->payments)
                    ->sum('amount_cents'),
            ];
        })->sortByDesc('total')->values()->all();

        if ($request->query('format') === 'csv') {
            $csvRows = collect($rows)->map(fn (array $row): array => [
                $row['name'],
                $row['total'],
                $row['in_progress'],
                $row['completed'],
                $row['cancelled'],
                $row['no_repair'],
                $row['pending'],
                $row['total_received_cents'],
            ]);

            return $this->downloadCsv(
                'relatorio-por-tecnico.csv',
                ['Técnico', 'Total', 'Em Andamento', 'Concluídas', 'Canceladas', 'Sem Reparo', 'Pendentes', 'Receita (centavos)'],
                $csvRows,
            );
        }

        return response()->json([
            'data' => $rows,
        ]);
    }

    protected function downloadCsv(string $fileName, array $header, Collection $rows)
    {
        return response()->streamDownload(function () use ($header, $rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $header, ';');

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function ensureServiceOrderBelongsToCurrentCompany(ServiceOrder $serviceOrder): void
    {
        abort_unless($serviceOrder->company_id === currentCompany()?->getKey(), Response::HTTP_NOT_FOUND);
    }
}
