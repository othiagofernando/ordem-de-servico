<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auditing\Actions\RecordBusinessAudit;
use App\Domains\Companies\Actions\ResolveCompanyOperationalSettings;
use App\Domains\ServiceOrders\Actions\RecordServiceOrderTimelineEvent;
use App\Domains\ServiceOrders\Actions\VisibleServiceOrders;
use App\Domains\ServiceOrders\Models\CompanyServiceOrderCounter;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderTimelineEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ServiceOrderWarrantyController extends Controller
{
    private const DEFAULT_WARRANTY_WINDOW_DAYS = 90;

    /**
     * Create a new warranty-return service order linked to the original delivered order.
     *
     * @group Service Orders
     */
    public function store(
        Request $request,
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        ResolveCompanyOperationalSettings $resolveCompanyOperationalSettings,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
        RecordBusinessAudit $recordBusinessAudit,
    ): JsonResponse {
        $original = $this->resolveManageableServiceOrder($serviceOrder, $visibleServiceOrders);
        $membership = currentMembership();

        if ($original->status !== ServiceOrder::STATUS_DELIVERED) {
            throw ValidationException::withMessages([
                'status' => 'Warranty returns can only be opened from delivered service orders.',
            ]);
        }

        $payload = $request->validate([
            'reported_problem' => ['required', 'string'],
        ]);

        [$signalStatus, $signalReason, $signalExpiresAt] = $this->computeWarrantySignal($original);

        $returnOrder = DB::transaction(function () use (
            $original,
            $payload,
            $membership,
            $signalStatus,
            $signalReason,
            $signalExpiresAt,
            $resolveCompanyOperationalSettings,
            $recordTimelineEvent,
        ): ServiceOrder {
            $orderNumber = $this->nextServiceOrderNumber($original->company_id);
            $original->loadMissing('company');
            $settings = $resolveCompanyOperationalSettings->handle($original->company);

            $returnOrder = ServiceOrder::query()->create([
                'company_id' => $original->company_id,
                'customer_id' => $original->customer_id,
                'customer_contact_id' => $original->customer_contact_id,
                'equipment_id' => $original->equipment_id,
                'intake_checklist_template_id' => $original->intake_checklist_template_id,
                'reference' => sprintf('%s-%06d', $settings->serviceOrderReferencePrefix(), $orderNumber),
                'order_number' => $orderNumber,
                'title' => $payload['reported_problem'],
                'status' => ServiceOrder::STATUS_OPEN,
                'equipment_description' => $original->equipment_description,
                'reported_problem' => $payload['reported_problem'],
                'intake_checklist' => $original->intake_checklist?->getArrayCopy() ?? $original->intake_checklist,
                'intake_checklist_template_version' => $original->intake_checklist_template_version,
                'intake_checklist_template_snapshot' => $original->intake_checklist_template_snapshot?->getArrayCopy()
                    ?? $original->intake_checklist_template_snapshot,
                'equipment_snapshot' => $original->equipment_snapshot?->getArrayCopy() ?? $original->equipment_snapshot,
                'opened_at' => now(),
                'warranty_origin_service_order_id' => $original->getKey(),
                'warranty_signal_status' => $signalStatus,
                'warranty_signal_reason' => $signalReason,
                'warranty_signal_expires_at' => $signalExpiresAt,
                'warranty_decision_status' => ServiceOrder::WARRANTY_DECISION_PENDING,
                'warranty_decision_override' => false,
            ]);

            $recordTimelineEvent->handle(
                $original,
                type: 'warranty_return_created',
                title: 'Warranty return created',
                visibility: ServiceOrderTimelineEvent::VISIBILITY_INTERNAL,
                actorMembership: $membership,
                payload: [
                    'warranty_return_service_order_id' => $returnOrder->getKey(),
                    'warranty_return_reference' => $returnOrder->reference,
                ],
            );

            $recordTimelineEvent->handle(
                $returnOrder,
                type: 'warranty_return_opened',
                title: 'Warranty return opened',
                visibility: ServiceOrderTimelineEvent::VISIBILITY_INTERNAL,
                actorMembership: $membership,
                payload: [
                    'warranty_origin_service_order_id' => $original->getKey(),
                    'warranty_signal_status' => $signalStatus,
                    'warranty_signal_reason' => $signalReason,
                ],
            );

            return $returnOrder->fresh([
                'customer',
                'customerContact',
                'equipment',
                'warrantyOriginServiceOrder',
            ]);
        });

        return response()->json([
            'data' => $this->serializeServiceOrder($returnOrder),
        ], Response::HTTP_CREATED);
    }

    /**
     * Confirm or override warranty coverage after inspection.
     *
     * @group Service Orders
     */
    public function decide(
        Request $request,
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
        RecordBusinessAudit $recordBusinessAudit,
    ): JsonResponse {
        $returnOrder = $this->resolveManageableServiceOrder($serviceOrder, $visibleServiceOrders);
        $membership = currentMembership();

        if ($returnOrder->warranty_origin_service_order_id === null) {
            throw ValidationException::withMessages([
                'warranty_origin_service_order_id' => 'Only warranty return service orders support warranty coverage decisions.',
            ]);
        }

        if ($returnOrder->warranty_decision_status !== ServiceOrder::WARRANTY_DECISION_PENDING) {
            throw ValidationException::withMessages([
                'warranty_decision_status' => 'Warranty coverage has already been decided for this service order.',
            ]);
        }

        $payload = $request->validate([
            'decision' => ['required', 'string', 'in:covered,not_covered'],
            'notes' => ['required', 'string'],
        ]);

        $decisionStatus = $payload['decision'] === 'covered'
            ? ServiceOrder::WARRANTY_DECISION_COVERED
            : ServiceOrder::WARRANTY_DECISION_NOT_COVERED;

        $expectedSignal = $decisionStatus === ServiceOrder::WARRANTY_DECISION_COVERED
            ? ServiceOrder::WARRANTY_SIGNAL_ELIGIBLE
            : ServiceOrder::WARRANTY_SIGNAL_INELIGIBLE;

        $isOverride = $returnOrder->warranty_signal_status !== ServiceOrder::WARRANTY_SIGNAL_UNKNOWN
            && $returnOrder->warranty_signal_status !== $expectedSignal;

        $returnOrder->forceFill([
            'warranty_decision_status' => $decisionStatus,
            'warranty_decision_override' => $isOverride,
            'warranty_decision_notes' => $payload['notes'],
            'warranty_decided_at' => now(),
            'warranty_decided_by_membership_id' => $membership?->getKey(),
        ])->save();

        $recordTimelineEvent->handle(
            $returnOrder,
            type: $isOverride ? 'warranty_coverage_overridden' : 'warranty_coverage_confirmed',
            title: $isOverride ? 'Warranty coverage overridden' : 'Warranty coverage confirmed',
            visibility: ServiceOrderTimelineEvent::VISIBILITY_INTERNAL,
            actorMembership: $membership,
            payload: [
                'decision_status' => $decisionStatus,
                'override' => $isOverride,
                'notes' => $payload['notes'],
            ],
        );

        if ($isOverride) {
            $recordBusinessAudit->handle(
                $returnOrder->company_id,
                'override',
                'warranty_coverage_overridden',
                auditable: $returnOrder,
                actorMembership: $membership,
                context: [
                    'service_order_id' => $returnOrder->getKey(),
                    'warranty_origin_service_order_id' => $returnOrder->warranty_origin_service_order_id,
                ],
                afterValues: [
                    'warranty_signal_status' => $returnOrder->warranty_signal_status,
                    'warranty_decision_status' => $decisionStatus,
                    'warranty_decision_notes' => $payload['notes'],
                ],
            );
        }

        return response()->json([
            'data' => $this->serializeServiceOrder($returnOrder->fresh([
                'customer',
                'customerContact',
                'equipment',
                'warrantyOriginServiceOrder',
            ])),
        ]);
    }

    protected function resolveManageableServiceOrder(
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
    ): ServiceOrder {
        $membership = currentMembership();

        if ($membership === null) {
            abort(Response::HTTP_FORBIDDEN, 'Missing active membership context.');
        }

        if (! $membership->hasPermission('service_orders.manage')) {
            abort(Response::HTTP_FORBIDDEN, 'This membership cannot manage warranty returns.');
        }

        $visible = $visibleServiceOrders->queryFor($membership)
            ->whereKey($serviceOrder->getKey())
            ->first();

        abort_if($visible === null, Response::HTTP_NOT_FOUND);

        return $visible;
    }

    /**
     * @return array{string, string, ?Carbon}
     */
    protected function computeWarrantySignal(ServiceOrder $original): array
    {
        if ($original->delivered_at === null) {
            return [
                ServiceOrder::WARRANTY_SIGNAL_UNKNOWN,
                'Original service order has no delivery date to evaluate warranty eligibility.',
                null,
            ];
        }

        $expiresAt = $original->delivered_at->copy()->addDays(self::DEFAULT_WARRANTY_WINDOW_DAYS);
        $isEligible = now()->lessThanOrEqualTo($expiresAt);

        return [
            $isEligible ? ServiceOrder::WARRANTY_SIGNAL_ELIGIBLE : ServiceOrder::WARRANTY_SIGNAL_INELIGIBLE,
            sprintf(
                'Initial warranty signal based on %d-day window from original delivery date.',
                self::DEFAULT_WARRANTY_WINDOW_DAYS,
            ),
            $expiresAt,
        ];
    }

    protected function nextServiceOrderNumber(int $companyId): int
    {
        return DB::transaction(function () use ($companyId): int {
            $counter = CompanyServiceOrderCounter::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if ($counter === null) {
                $counter = CompanyServiceOrderCounter::query()->create([
                    'company_id' => $companyId,
                    'next_draft_number' => 1,
                    'next_service_order_number' => 1,
                ]);
            }

            $currentNumber = $counter->next_service_order_number;

            $counter->forceFill([
                'next_service_order_number' => $currentNumber + 1,
            ])->save();

            return $currentNumber;
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeServiceOrder(ServiceOrder $serviceOrder): array
    {
        return [
            'id' => $serviceOrder->getKey(),
            'reference' => $serviceOrder->reference,
            'order_number' => $serviceOrder->order_number,
            'status' => $serviceOrder->status,
            'title' => $serviceOrder->title,
            'reported_problem' => $serviceOrder->reported_problem,
            'customer_id' => $serviceOrder->customer_id,
            'customer_contact_id' => $serviceOrder->customer_contact_id,
            'equipment_id' => $serviceOrder->equipment_id,
            'warranty_origin_service_order_id' => $serviceOrder->warranty_origin_service_order_id,
            'warranty_signal_status' => $serviceOrder->warranty_signal_status,
            'warranty_signal_reason' => $serviceOrder->warranty_signal_reason,
            'warranty_signal_expires_at' => $serviceOrder->warranty_signal_expires_at?->toIso8601String(),
            'warranty_decision_status' => $serviceOrder->warranty_decision_status,
            'warranty_decision_override' => (bool) $serviceOrder->warranty_decision_override,
            'warranty_decision_notes' => $serviceOrder->warranty_decision_notes,
            'warranty_decided_at' => $serviceOrder->warranty_decided_at?->toIso8601String(),
            'warranty_decided_by_membership_id' => $serviceOrder->warranty_decided_by_membership_id,
        ];
    }
}
