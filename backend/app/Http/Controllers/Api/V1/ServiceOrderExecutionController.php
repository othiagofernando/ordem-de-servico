<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Notifications\Actions\QueueCriticalServiceOrderNotification;
use App\Domains\ServiceOrders\Actions\RecordServiceOrderTimelineEvent;
use App\Domains\ServiceOrders\Actions\VisibleServiceOrders;
use App\Domains\ServiceOrders\Models\QuoteRevision;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderExecutionEntry;
use App\Domains\ServiceOrders\Models\ServiceOrderTimelineEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ServiceOrderExecutionController extends Controller
{
    /**
     * Explicitly start execution after quote approval and technician assignment.
     *
     * @group Service Orders
     */
    public function start(
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
        QueueCriticalServiceOrderNotification $queueCriticalServiceOrderNotification,
    ): JsonResponse {
        $membership = currentMembership();
        $visible = $this->resolveExecutableServiceOrder($serviceOrder, $visibleServiceOrders);

        if ($visible->assigned_membership_id === null) {
            throw ValidationException::withMessages([
                'assigned_membership_id' => 'A responsible technician must be assigned before execution starts.',
            ]);
        }

        if (! $visible->quoteRevisions()->where('status', QuoteRevision::STATUS_APPROVED)->exists()) {
            throw ValidationException::withMessages([
                'quote_revision' => 'An approved quote is required before execution starts.',
            ]);
        }

        if ($visible->execution_started_at !== null) {
            return response()->json([
                'data' => $this->serializeServiceOrder($visible->fresh(['executionEntries'])),
            ]);
        }

        $visible->forceFill([
            'status' => ServiceOrder::STATUS_IN_PROGRESS,
            'execution_started_at' => now(),
            'execution_started_by_membership_id' => $membership?->getKey(),
        ])->save();

        $recordTimelineEvent->handle(
            $visible,
            type: 'execution_started',
            title: 'Execution started',
            visibility: ServiceOrderTimelineEvent::VISIBILITY_CUSTOMER,
            actorMembership: $membership,
            payload: [
                'assigned_membership_id' => $visible->assigned_membership_id,
            ],
        );

        $fresh = $visible->fresh(['customerContact', 'company']);
        $queueCriticalServiceOrderNotification->handle(
            $fresh,
            'service_order_in_progress',
            [
                'service_order_reference' => $fresh->reference,
                'status' => $fresh->status,
            ],
        );

        return response()->json([
            'data' => $this->serializeServiceOrder($visible->fresh(['executionEntries'])),
        ]);
    }

    /**
     * Record executed services and parts used after execution has started.
     *
     * @group Service Orders
     */
    public function recordWork(
        Request $request,
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
    ): JsonResponse {
        $membership = currentMembership();
        $visible = $this->resolveExecutableServiceOrder($serviceOrder, $visibleServiceOrders);

        if ($visible->execution_started_at === null) {
            throw ValidationException::withMessages([
                'execution_started_at' => 'Execution must be started before work records can be added.',
            ]);
        }

        $payload = $request->validate([
            'services' => ['sometimes', 'array'],
            'services.*.description' => ['required_with:services', 'string', 'max:255'],
            'services.*.quantity' => ['nullable', 'integer', 'min:1'],
            'services.*.notes' => ['nullable', 'string'],
            'parts' => ['sometimes', 'array'],
            'parts.*.description' => ['required_with:parts', 'string', 'max:255'],
            'parts.*.quantity' => ['nullable', 'integer', 'min:1'],
            'parts.*.notes' => ['nullable', 'string'],
        ]);

        if (empty($payload['services'] ?? []) && empty($payload['parts'] ?? [])) {
            throw ValidationException::withMessages([
                'services' => 'Provide at least one service or part entry.',
            ]);
        }

        DB::transaction(function () use ($visible, $payload, $membership, $recordTimelineEvent): void {
            foreach ($payload['services'] ?? [] as $service) {
                ServiceOrderExecutionEntry::query()->create([
                    'company_id' => $visible->company_id,
                    'service_order_id' => $visible->getKey(),
                    'membership_id' => $membership?->getKey(),
                    'entry_type' => ServiceOrderExecutionEntry::TYPE_SERVICE,
                    'description' => $service['description'],
                    'quantity' => $service['quantity'] ?? 1,
                    'notes' => $service['notes'] ?? null,
                ]);
            }

            foreach ($payload['parts'] ?? [] as $part) {
                ServiceOrderExecutionEntry::query()->create([
                    'company_id' => $visible->company_id,
                    'service_order_id' => $visible->getKey(),
                    'membership_id' => $membership?->getKey(),
                    'entry_type' => ServiceOrderExecutionEntry::TYPE_PART,
                    'description' => $part['description'],
                    'quantity' => $part['quantity'] ?? 1,
                    'notes' => $part['notes'] ?? null,
                ]);
            }

            $recordTimelineEvent->handle(
                $visible,
                type: 'execution_work_recorded',
                title: 'Execution work recorded',
                visibility: ServiceOrderTimelineEvent::VISIBILITY_INTERNAL,
                actorMembership: $membership,
                payload: [
                    'services_count' => count($payload['services'] ?? []),
                    'parts_count' => count($payload['parts'] ?? []),
                ],
            );
        });

        return response()->json([
            'data' => $this->serializeServiceOrder($visible->fresh(['executionEntries'])),
        ]);
    }

    /**
     * Mark technical completion after final test results have been captured.
     *
     * @group Service Orders
     */
    public function complete(
        Request $request,
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
        QueueCriticalServiceOrderNotification $queueCriticalServiceOrderNotification,
    ): JsonResponse {
        $membership = currentMembership();
        $visible = $this->resolveExecutableServiceOrder($serviceOrder, $visibleServiceOrders);

        if ($visible->execution_started_at === null) {
            throw ValidationException::withMessages([
                'execution_started_at' => 'Execution must be started before technical completion.',
            ]);
        }

        $payload = $request->validate([
            'final_test_results' => ['required', 'array', 'min:1'],
            'final_test_results.*.label' => ['required', 'string', 'max:255'],
            'final_test_results.*.passed' => ['required', 'boolean'],
            'final_test_results.*.notes' => ['nullable', 'string'],
        ]);

        $visible->forceFill([
            'status' => ServiceOrder::STATUS_COMPLETED,
            'final_test_results' => $payload['final_test_results'],
            'technical_completed_at' => now(),
        ])->save();

        $recordTimelineEvent->handle(
            $visible,
            type: 'technical_completion_recorded',
            title: 'Technical completion recorded',
            visibility: ServiceOrderTimelineEvent::VISIBILITY_CUSTOMER,
            actorMembership: $membership,
            payload: [
                'final_test_results_count' => count($payload['final_test_results']),
                'status' => $visible->status,
            ],
        );

        $fresh = $visible->fresh(['customerContact', 'company']);
        $queueCriticalServiceOrderNotification->handle(
            $fresh,
            'service_order_completed',
            [
                'service_order_reference' => $fresh->reference,
                'status' => $fresh->status,
            ],
        );

        return response()->json([
            'data' => $this->serializeServiceOrder($visible->fresh(['executionEntries'])),
        ]);
    }

    protected function resolveExecutableServiceOrder(
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
    ): ServiceOrder {
        $membership = currentMembership();

        if ($membership === null) {
            abort(Response::HTTP_FORBIDDEN, 'Missing active membership context.');
        }

        if (! $membership->hasPermission('service_orders.execute')) {
            abort(Response::HTTP_FORBIDDEN, 'This membership cannot execute service orders.');
        }

        $visible = $visibleServiceOrders->queryFor($membership)
            ->whereKey($serviceOrder->getKey())
            ->first();

        abort_if($visible === null, Response::HTTP_NOT_FOUND);

        return $visible;
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
            'assigned_membership_id' => $serviceOrder->assigned_membership_id,
            'execution_started_at' => $serviceOrder->execution_started_at?->toIso8601String(),
            'execution_started_by_membership_id' => $serviceOrder->execution_started_by_membership_id,
            'technical_completed_at' => $serviceOrder->technical_completed_at?->toIso8601String(),
            'final_test_results' => $serviceOrder->final_test_results,
            'execution_entries' => $serviceOrder->executionEntries->map(fn (ServiceOrderExecutionEntry $entry): array => [
                'id' => $entry->getKey(),
                'entry_type' => $entry->entry_type,
                'description' => $entry->description,
                'quantity' => $entry->quantity,
                'notes' => $entry->notes,
                'membership_id' => $entry->membership_id,
            ])->all(),
        ];
    }
}
