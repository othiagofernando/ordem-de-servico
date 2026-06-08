<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerContact;
use App\Domains\Equipments\Models\Equipment;
use App\Domains\ServiceOrders\Actions\OpenDraftServiceOrder;
use App\Domains\ServiceOrders\Actions\RecordServiceOrderTimelineEvent;
use App\Domains\ServiceOrders\Actions\ReserveDraftReference;
use App\Domains\ServiceOrders\Actions\VisibleServiceOrders;
use App\Domains\ServiceOrders\Models\IntakeChecklistTemplate;
use App\Domains\ServiceOrders\Models\QuoteRevision;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderTimelineEvent;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ServiceOrderController extends Controller
{
    /**
     * Create a draft intake record with a temporary visible reference.
     *
     * @group Service Orders
     */
    public function storeDraft(Request $request, ReserveDraftReference $reserveDraftReference): JsonResponse
    {
        $payload = $this->validateDraftPayload($request);

        $serviceOrder = DB::transaction(function () use ($payload, $reserveDraftReference): ServiceOrder {
            $companyId = currentCompany()?->getKey();
            $draftReference = $reserveDraftReference->handle($companyId);

            $serviceOrder = ServiceOrder::query()->create([
                'company_id' => $companyId,
                'customer_id' => $payload['customer_id'] ?? null,
                'customer_contact_id' => $payload['customer_contact_id'] ?? null,
                'equipment_id' => $payload['equipment_id'] ?? null,
                'intake_checklist_template_id' => $payload['intake_checklist_template_id'] ?? null,
                'reference' => $draftReference,
                'draft_reference' => $draftReference,
                'status' => ServiceOrder::STATUS_DRAFT,
                'title' => $payload['reported_problem'] ?? $payload['equipment_description'] ?? 'Draft intake',
                'equipment_description' => $payload['equipment_description'] ?? null,
                'reported_problem' => $payload['reported_problem'] ?? null,
                'intake_checklist' => $payload['intake_checklist'] ?? null,
            ]);

            app(RecordServiceOrderTimelineEvent::class)->handle(
                $serviceOrder,
                type: 'draft_intake_started',
                title: 'Draft intake started',
                visibility: ServiceOrderTimelineEvent::VISIBILITY_INTERNAL,
                actorMembership: currentMembership(),
                payload: [
                    'draft_reference' => $draftReference,
                ],
            );

            return $serviceOrder->load(['customer', 'customerContact', 'equipment', 'assignedMembership']);
        });

        return response()->json([
            'data' => $this->serializeServiceOrder($serviceOrder),
        ], Response::HTTP_CREATED);
    }

    /**
     * Update an existing draft intake record so staff can resume and complete it later.
     *
     * @group Service Orders
     */
    public function updateDraft(Request $request, ServiceOrder $serviceOrder): JsonResponse
    {
        $this->ensureServiceOrderBelongsToCurrentCompany($serviceOrder);

        if ($serviceOrder->status !== ServiceOrder::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only draft intake records can be updated through this endpoint.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $payload = $this->validateDraftPayload($request);

        $attributes = [];

        foreach ([
            'customer_id',
            'customer_contact_id',
            'equipment_id',
            'intake_checklist_template_id',
            'equipment_description',
            'reported_problem',
            'intake_checklist',
        ] as $field) {
            if ($request->exists($field)) {
                $attributes[$field] = $payload[$field] ?? null;
            }
        }

        if ($request->exists('reported_problem') || $request->exists('equipment_description')) {
            $attributes['title'] = $payload['reported_problem'] ?? $payload['equipment_description'] ?? 'Draft intake';
        }

        $serviceOrder->forceFill($attributes)->save();

        return response()->json([
            'data' => $this->serializeServiceOrder($serviceOrder->load(['customer', 'customerContact', 'equipment', 'assignedMembership'])),
        ]);
    }

    /**
     * Formally open a service order once minimum intake data has been completed.
     *
     * @group Service Orders
     */
    public function openDraft(ServiceOrder $serviceOrder, OpenDraftServiceOrder $openDraftServiceOrder): JsonResponse
    {
        $this->ensureServiceOrderBelongsToCurrentCompany($serviceOrder);
        $this->validateOpenableDraft($serviceOrder);

        $opened = $openDraftServiceOrder->handle($serviceOrder);

        return response()->json([
            'data' => $this->serializeServiceOrder($opened),
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
    ): JsonResponse {
        $membership = currentMembership();

        if ($membership === null) {
            return response()->json(['message' => 'Missing active membership context.'], Response::HTTP_FORBIDDEN);
        }

        if (! $membership->hasPermission('service_orders.diagnose')) {
            return response()->json([
                'message' => 'This membership cannot record diagnosis.',
            ], Response::HTTP_FORBIDDEN);
        }

        $diagnosable = $visibleServiceOrders->queryFor($membership)
            ->whereKey($serviceOrder->getKey())
            ->first();

        abort_if($diagnosable === null, Response::HTTP_NOT_FOUND);

        $payload = $request->validate([
            'internal_notes' => ['required', 'string'],
            'customer_summary' => ['required', 'string'],
        ]);

        $diagnosable->forceFill([
            'diagnosis_internal_notes' => $payload['internal_notes'],
            'diagnosis_customer_summary' => $payload['customer_summary'],
            'diagnosis_completed_at' => now(),
        ])->save();

        $recordTimelineEvent->handle(
            $diagnosable,
            type: 'diagnosis_recorded',
            title: 'Diagnosis recorded',
            visibility: ServiceOrderTimelineEvent::VISIBILITY_INTERNAL,
            actorMembership: $membership,
        );

        $recordTimelineEvent->handle(
            $diagnosable,
            type: 'diagnosis_customer_summary_updated',
            title: 'Diagnosis customer summary updated',
            visibility: ServiceOrderTimelineEvent::VISIBILITY_CUSTOMER,
            actorMembership: $membership,
        );

        return response()->json([
            'data' => $this->serializeServiceOrder($diagnosable->fresh([
                'customer',
                'customerContact',
                'equipment',
                'assignedMembership',
            ])),
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

        $serviceOrders = $this->buildFilteredVisibleQuery(request(), $visibleServiceOrders, $membership)
            ->with(['customer', 'customerContact', 'equipment', 'assignedMembership.user', 'quoteRevisions'])
            ->get();

        return response()->json([
            'data' => $serviceOrders->map(fn (ServiceOrder $serviceOrder): array => $this->serializeServiceOrder($serviceOrder))->all(),
            'meta' => [
                'total' => $serviceOrders->count(),
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
                'filters' => $request->only(['q', 'queue', 'assigned_membership_id', 'status']),
                'totals' => [
                    'service_orders' => $serviceOrders->count(),
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
                        ->groupBy(fn (ServiceOrder $serviceOrder): string => $serviceOrder->warranty_decision_status ?? 'unreviewed')
                        ->map(fn (Collection $orders): int => $orders->count())
                        ->all(),
                ],
            ],
        ]);
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

        return response()->json([
            'data' => $this->serializeServiceOrder($visible->load(['customer', 'customerContact', 'equipment', 'assignedMembership'])),
        ]);
    }

    protected function validateDraftPayload(Request $request): array
    {
        $payload = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'customer_contact_id' => ['nullable', 'integer'],
            'equipment_id' => ['nullable', 'integer'],
            'intake_checklist_template_id' => ['nullable', 'integer'],
            'equipment_description' => ['nullable', 'string'],
            'reported_problem' => ['nullable', 'string'],
            'intake_checklist' => ['nullable', 'array'],
            'intake_checklist.*.label' => ['required_with:intake_checklist', 'string', 'max:255'],
            'intake_checklist.*.checked' => ['nullable', 'boolean'],
            'intake_checklist.*.notes' => ['nullable', 'string'],
        ]);

        $customer = null;

        if (($payload['customer_id'] ?? null) !== null) {
            $customer = Customer::query()->findOrFail($payload['customer_id']);
            abort_unless($customer->company_id === currentCompany()?->getKey(), Response::HTTP_NOT_FOUND);
        }

        if (($payload['customer_contact_id'] ?? null) !== null) {
            $contact = CustomerContact::query()->findOrFail($payload['customer_contact_id']);
            abort_unless($contact->company_id === currentCompany()?->getKey(), Response::HTTP_NOT_FOUND);
            abort_unless($customer !== null && $contact->customer_id === $customer->getKey(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (($payload['equipment_id'] ?? null) !== null) {
            $equipment = Equipment::query()->findOrFail($payload['equipment_id']);
            abort_unless($equipment->company_id === currentCompany()?->getKey(), Response::HTTP_NOT_FOUND);
            abort_unless($customer !== null && $equipment->customer_id === $customer->getKey(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (($payload['intake_checklist_template_id'] ?? null) !== null) {
            $template = IntakeChecklistTemplate::query()->findOrFail($payload['intake_checklist_template_id']);
            abort_unless($template->company_id === currentCompany()?->getKey(), Response::HTTP_NOT_FOUND);

            if (($payload['equipment_id'] ?? null) !== null) {
                abort_unless(
                    isset($equipment) && $template->category === $equipment->category,
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }
        }

        return $payload;
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

    /**
     * @return array<string, mixed>
     */
    protected function serializeServiceOrder(ServiceOrder $serviceOrder): array
    {
        $latestQuote = $serviceOrder->relationLoaded('quoteRevisions')
            ? $serviceOrder->quoteRevisions->sortByDesc('revision_number')->first()
            : null;

        return [
            'id' => $serviceOrder->getKey(),
            'reference' => $serviceOrder->reference,
            'draft_reference' => $serviceOrder->draft_reference,
            'order_number' => $serviceOrder->order_number,
            'title' => $serviceOrder->title,
            'status' => $serviceOrder->status,
            'customer_id' => $serviceOrder->customer_id,
            'customer_contact_id' => $serviceOrder->customer_contact_id,
            'equipment_id' => $serviceOrder->equipment_id,
            'equipment_description' => $serviceOrder->equipment_description,
            'reported_problem' => $serviceOrder->reported_problem,
            'intake_checklist' => $serviceOrder->intake_checklist,
            'intake_checklist_template_id' => $serviceOrder->intake_checklist_template_id,
            'intake_checklist_template_version' => $serviceOrder->intake_checklist_template_version,
            'intake_checklist_template_snapshot' => $serviceOrder->intake_checklist_template_snapshot,
            'equipment_snapshot' => $serviceOrder->equipment_snapshot,
            'diagnosis_internal_notes' => $serviceOrder->diagnosis_internal_notes,
            'diagnosis_customer_summary' => $serviceOrder->diagnosis_customer_summary,
            'diagnosis_completed_at' => $serviceOrder->diagnosis_completed_at?->toIso8601String(),
            'technical_completed_at' => $serviceOrder->technical_completed_at?->toIso8601String(),
            'delivered_at' => $serviceOrder->delivered_at?->toIso8601String(),
            'delivered_by_membership_id' => $serviceOrder->delivered_by_membership_id,
            'received_by_name' => $serviceOrder->received_by_name,
            'delivery_notes' => $serviceOrder->delivery_notes,
            'terminalized_at' => $serviceOrder->terminalized_at?->toIso8601String(),
            'terminalized_by_membership_id' => $serviceOrder->terminalized_by_membership_id,
            'terminal_notes' => $serviceOrder->terminal_notes,
            'warranty_origin_service_order_id' => $serviceOrder->warranty_origin_service_order_id,
            'warranty_signal_status' => $serviceOrder->warranty_signal_status,
            'warranty_signal_reason' => $serviceOrder->warranty_signal_reason,
            'warranty_signal_expires_at' => $serviceOrder->warranty_signal_expires_at?->toIso8601String(),
            'warranty_decision_status' => $serviceOrder->warranty_decision_status,
            'warranty_decision_override' => $serviceOrder->warranty_decision_override,
            'warranty_decision_notes' => $serviceOrder->warranty_decision_notes,
            'warranty_decided_at' => $serviceOrder->warranty_decided_at?->toIso8601String(),
            'warranty_decided_by_membership_id' => $serviceOrder->warranty_decided_by_membership_id,
            'opened_at' => $serviceOrder->opened_at?->toIso8601String(),
            'assigned_membership_id' => $serviceOrder->assigned_membership_id,
            'assigned_membership_name' => $serviceOrder->assignedMembership?->user?->name,
            'customer_name' => $serviceOrder->customer?->name,
            'customer_document' => $serviceOrder->customer?->document,
            'customer_contact_name' => $serviceOrder->customerContact?->name,
            'customer_contact_phone' => $serviceOrder->customerContact?->phone,
            'equipment_serial_number' => $serviceOrder->equipment?->serial_number,
            'equipment_brand' => $serviceOrder->equipment?->brand,
            'equipment_model' => $serviceOrder->equipment?->model,
            'latest_quote_status' => $latestQuote?->status,
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
        ]);

        $query = $visibleServiceOrders->queryFor($membership);

        if (filled($filters['assigned_membership_id'] ?? null)) {
            $query->where('assigned_membership_id', $filters['assigned_membership_id']);
        }

        if (($filters['status'] ?? null) !== null) {
            $query->whereIn('status', $filters['status']);
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

    protected function ensureServiceOrderBelongsToCurrentCompany(ServiceOrder $serviceOrder): void
    {
        abort_unless($serviceOrder->company_id === currentCompany()?->getKey(), Response::HTTP_NOT_FOUND);
    }
}
