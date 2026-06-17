<?php

namespace App\Domains\ServiceOrders\Actions;

use App\Domains\Auth\Models\Membership;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use Symfony\Component\HttpFoundation\Response;

class ResolveDiagnosableServiceOrder
{
    public function handle(ServiceOrder $serviceOrder, VisibleServiceOrders $visibleServiceOrders): ServiceOrder
    {
        $membership = currentMembership();

        if ($membership === null) {
            abort(Response::HTTP_FORBIDDEN, 'Missing active membership context.');
        }

        if (! $membership->hasPermission('service_orders.diagnose')) {
            abort(Response::HTTP_FORBIDDEN, 'This membership cannot record diagnosis.');
        }

        $diagnosable = $visibleServiceOrders->queryFor($membership)
            ->whereKey($serviceOrder->getKey())
            ->first();

        abort_if($diagnosable === null, Response::HTTP_NOT_FOUND);

        return $diagnosable;
    }

    public function membership(): ?Membership
    {
        return currentMembership();
    }
}
