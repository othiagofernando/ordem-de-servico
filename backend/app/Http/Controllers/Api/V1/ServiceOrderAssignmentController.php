<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auth\Models\Membership;
use App\Domains\Notifications\Actions\CreateUserNotification;
use App\Domains\ServiceOrders\Actions\RecordServiceOrderTimelineEvent;
use App\Domains\ServiceOrders\Actions\VisibleServiceOrders;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderTimelineEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ServiceOrderAssignmentController extends Controller
{
    /**
     * Assign or unassign a technician to a service order.
     *
     * @group Service Orders
     */
    public function __invoke(
        Request $request,
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
        CreateUserNotification $createUserNotification,
    ): JsonResponse {
        $membership = currentMembership();

        if ($membership === null) {
            abort(Response::HTTP_FORBIDDEN, 'Missing active membership context.');
        }

        if (! $membership->hasPermission('service_orders.manage')) {
            abort(Response::HTTP_FORBIDDEN, 'This membership cannot manage service orders.');
        }

        $visible = $visibleServiceOrders->queryFor($membership)
            ->whereKey($serviceOrder->getKey())
            ->first();

        abort_if($visible === null, Response::HTTP_NOT_FOUND);

        if (! in_array($visible->status, [
            ServiceOrder::STATUS_OPEN,
            ServiceOrder::STATUS_IN_PROGRESS,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Technician assignment can only be changed while the service order is open or in progress.',
            ]);
        }

        $payload = $request->validate([
            'assigned_membership_id' => ['nullable', 'integer'],
        ]);

        $assignedMembershipId = $payload['assigned_membership_id'] ?? null;

        if ($assignedMembershipId !== null) {
            $assignedMembership = Membership::query()
                ->where('company_id', $visible->company_id)
                ->where('status', Membership::STATUS_ACTIVE)
                ->findOrFail($assignedMembershipId);

            $visible->forceFill([
                'assigned_membership_id' => $assignedMembership->getKey(),
            ])->save();

            $recordTimelineEvent->handle(
                $visible,
                type: 'technician_assigned',
                title: 'Technician assigned',
                visibility: ServiceOrderTimelineEvent::VISIBILITY_INTERNAL,
                actorMembership: $membership,
                payload: [
                    'assigned_membership_id' => $assignedMembership->getKey(),
                ],
            );

            // Notify newly assigned technician (skip if self-assignment)
            if ($assignedMembership->getKey() !== $membership->getKey()) {
                try {
                    $visible->loadMissing('company');
                    $companySlug = $visible->company->slug ?? '';
                    $createUserNotification->handle(
                        companyId: $visible->company_id,
                        membershipId: $assignedMembership->getKey(),
                        type: 'os_assigned',
                        title: 'OS atribuída a você',
                        body: "Você foi designado para a O.S. {$visible->reference}.",
                        actionUrl: "/{$companySlug}/service-orders/{$visible->id}",
                    );
                } catch (\Throwable) {
                    // Notification failure must not break the main flow
                }
            }
        } else {
            $visible->forceFill([
                'assigned_membership_id' => null,
            ])->save();

            $recordTimelineEvent->handle(
                $visible,
                type: 'technician_unassigned',
                title: 'Technician unassigned',
                visibility: ServiceOrderTimelineEvent::VISIBILITY_INTERNAL,
                actorMembership: $membership,
            );
        }

        return response()->json([
            'data' => [
                'id' => $visible->getKey(),
                'assigned_membership_id' => $visible->assigned_membership_id,
            ],
        ]);
    }
}
