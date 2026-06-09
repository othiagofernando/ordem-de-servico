<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auditing\Actions\RecordBusinessAudit;
use App\Domains\Notifications\Actions\QueueCriticalServiceOrderNotification;
use App\Domains\ServiceOrders\Actions\RecordServiceOrderTimelineEvent;
use App\Domains\ServiceOrders\Actions\VisibleServiceOrders;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderTimelineEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ServiceOrderClosureController extends Controller
{
    /**
     * Mark a technically completed service order as ready for pickup.
     *
     * @group Service Orders
     */
    public function readyForPickup(
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
        QueueCriticalServiceOrderNotification $queueCriticalServiceOrderNotification,
    ): JsonResponse {
        $visible = $this->resolveClosableServiceOrder($serviceOrder, $visibleServiceOrders);
        $membership = currentMembership();

        $this->ensureNotTerminal($visible);

        if ($visible->status !== ServiceOrder::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'status' => 'Only technically completed service orders can be marked as ready for pickup.',
            ]);
        }

        $visible->forceFill([
            'status' => ServiceOrder::STATUS_READY_FOR_PICKUP,
        ])->save();

        $recordTimelineEvent->handle(
            $visible,
            type: 'service_order_ready_for_pickup',
            title: 'Service order ready for pickup',
            visibility: ServiceOrderTimelineEvent::VISIBILITY_CUSTOMER,
            actorMembership: $membership,
        );

        $fresh = $visible->fresh(['customerContact', 'company']);

        $queueCriticalServiceOrderNotification->handle(
            $fresh,
            'ready_for_pickup',
            [
                'service_order_reference' => $fresh->reference,
                'status' => $fresh->status,
            ],
        );

        return response()->json([
            'data' => $this->serializeServiceOrder($fresh),
        ]);
    }

    /**
     * Deliver a ready-for-pickup service order with structured handoff evidence.
     *
     * @group Service Orders
     */
    public function deliver(
        Request $request,
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
        RecordBusinessAudit $recordBusinessAudit,
    ): JsonResponse {
        $visible = $this->resolveClosableServiceOrder($serviceOrder, $visibleServiceOrders);
        $membership = currentMembership();

        $this->ensureNotTerminal($visible);

        if ($visible->status !== ServiceOrder::STATUS_READY_FOR_PICKUP) {
            throw ValidationException::withMessages([
                'status' => 'Only service orders ready for pickup can be delivered.',
            ]);
        }

        $payload = $request->validate([
            'received_by_name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'allow_balance_override' => ['sometimes', 'boolean'],
            'balance_override_reason' => ['nullable', 'string'],
        ]);

        $outstandingBalanceCents = $this->outstandingBalanceCents($visible);
        $allowBalanceOverride = (bool) ($payload['allow_balance_override'] ?? false);

        if ($outstandingBalanceCents > 0 && ! $allowBalanceOverride) {
            throw ValidationException::withMessages([
                'outstanding_balance_cents' => 'Outstanding balance must be settled before delivery unless a privileged override is used.',
            ]);
        }

        if ($outstandingBalanceCents > 0 && $allowBalanceOverride) {
            if (! $membership?->hasPermission('service_orders.override_delivery_balance')) {
                abort(Response::HTTP_FORBIDDEN, 'This membership cannot override delivery balance gates.');
            }

            if (! filled($payload['balance_override_reason'] ?? null)) {
                throw ValidationException::withMessages([
                    'balance_override_reason' => 'A balance override reason is required when delivering with outstanding balance.',
                ]);
            }

            $recordTimelineEvent->handle(
                $visible,
                type: 'delivery_balance_override_used',
                title: 'Delivery balance override used',
                visibility: ServiceOrderTimelineEvent::VISIBILITY_INTERNAL,
                actorMembership: $membership,
                payload: [
                    'outstanding_balance_cents' => $outstandingBalanceCents,
                    'reason' => $payload['balance_override_reason'],
                ],
            );

            $recordBusinessAudit->handle(
                $visible->company_id,
                'override',
                'service_order_delivery_balance_overridden',
                auditable: $visible,
                actorMembership: $membership,
                context: [
                    'service_order_id' => $visible->getKey(),
                    'service_order_reference' => $visible->reference,
                ],
                afterValues: [
                    'outstanding_balance_cents' => $outstandingBalanceCents,
                    'reason' => $payload['balance_override_reason'],
                ],
            );
        }

        $visible->forceFill([
            'status' => ServiceOrder::STATUS_DELIVERED,
            'delivered_at' => now(),
            'delivered_by_membership_id' => $membership?->getKey(),
            'received_by_name' => $payload['received_by_name'],
            'delivery_notes' => $payload['notes'] ?? null,
        ])->save();

        $recordTimelineEvent->handle(
            $visible,
            type: 'service_order_delivered',
            title: 'Service order delivered',
            visibility: ServiceOrderTimelineEvent::VISIBILITY_CUSTOMER,
            actorMembership: $membership,
            payload: [
                'received_by_name' => $visible->received_by_name,
                'delivered_at' => $visible->delivered_at?->toIso8601String(),
                'outstanding_balance_cents' => $outstandingBalanceCents,
            ],
        );

        return response()->json([
            'data' => $this->serializeServiceOrder($visible->fresh()),
        ]);
    }

    /**
     * Close a service order as cancelled.
     *
     * @group Service Orders
     */
    public function cancel(
        Request $request,
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
    ): JsonResponse {
        return $this->closeTerminal(
            $request,
            $serviceOrder,
            $visibleServiceOrders,
            $recordTimelineEvent,
            ServiceOrder::STATUS_CANCELLED,
            'service_order_cancelled',
            'Service order cancelled',
        );
    }

    /**
     * Close a service order as no-repair.
     *
     * @group Service Orders
     */
    public function noRepair(
        Request $request,
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
    ): JsonResponse {
        return $this->closeTerminal(
            $request,
            $serviceOrder,
            $visibleServiceOrders,
            $recordTimelineEvent,
            ServiceOrder::STATUS_NO_REPAIR,
            'service_order_closed_no_repair',
            'Service order closed as no repair',
        );
    }

    protected function closeTerminal(
        Request $request,
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
        string $status,
        string $timelineType,
        string $timelineTitle,
    ): JsonResponse {
        $visible = $this->resolveClosableServiceOrder($serviceOrder, $visibleServiceOrders);
        $membership = currentMembership();

        $this->ensureNotTerminal($visible);

        if ($visible->status === ServiceOrder::STATUS_DELIVERED) {
            throw ValidationException::withMessages([
                'status' => 'Delivered service orders cannot be closed with another terminal outcome.',
            ]);
        }

        $payload = $request->validate([
            'notes' => ['required', 'string'],
        ]);

        $visible->forceFill([
            'status' => $status,
            'terminalized_at' => now(),
            'terminalized_by_membership_id' => $membership?->getKey(),
            'terminal_notes' => $payload['notes'],
        ])->save();

        $recordTimelineEvent->handle(
            $visible,
            type: $timelineType,
            title: $timelineTitle,
            visibility: ServiceOrderTimelineEvent::VISIBILITY_CUSTOMER,
            actorMembership: $membership,
            payload: [
                'status' => $visible->status,
                'notes' => $visible->terminal_notes,
            ],
        );

        return response()->json([
            'data' => $this->serializeServiceOrder($visible->fresh()),
        ]);
    }

    protected function resolveClosableServiceOrder(
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
    ): ServiceOrder {
        $membership = currentMembership();

        if ($membership === null) {
            abort(Response::HTTP_FORBIDDEN, 'Missing active membership context.');
        }

        if (! $membership->hasPermission('service_orders.manage')) {
            abort(Response::HTTP_FORBIDDEN, 'This membership cannot close service orders.');
        }

        $visible = $visibleServiceOrders->queryFor($membership)
            ->whereKey($serviceOrder->getKey())
            ->first();

        abort_if($visible === null, Response::HTTP_NOT_FOUND);

        return $visible->loadMissing(['charges', 'payments']);
    }

    protected function outstandingBalanceCents(ServiceOrder $serviceOrder): int
    {
        return (int) $serviceOrder->charges->sum('amount_cents') - (int) $serviceOrder->payments->sum('amount_cents');
    }

    protected function ensureNotTerminal(ServiceOrder $serviceOrder): void
    {
        if (in_array($serviceOrder->status, [
            ServiceOrder::STATUS_DELIVERED,
            ServiceOrder::STATUS_CANCELLED,
            ServiceOrder::STATUS_NO_REPAIR,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Terminal service orders do not support further standard workflow transitions.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeServiceOrder(ServiceOrder $serviceOrder): array
    {
        return [
            'id' => $serviceOrder->getKey(),
            'reference' => $serviceOrder->reference,
            'status' => $serviceOrder->status,
            'technical_completed_at' => $serviceOrder->technical_completed_at?->toIso8601String(),
            'delivered_at' => $serviceOrder->delivered_at?->toIso8601String(),
            'delivered_by_membership_id' => $serviceOrder->delivered_by_membership_id,
            'received_by_name' => $serviceOrder->received_by_name,
            'delivery_notes' => $serviceOrder->delivery_notes,
            'terminalized_at' => $serviceOrder->terminalized_at?->toIso8601String(),
            'terminalized_by_membership_id' => $serviceOrder->terminalized_by_membership_id,
            'terminal_notes' => $serviceOrder->terminal_notes,
        ];
    }
}
