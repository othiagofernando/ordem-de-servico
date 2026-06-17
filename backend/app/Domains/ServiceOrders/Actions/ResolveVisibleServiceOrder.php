<?php

namespace App\Domains\ServiceOrders\Actions;

use App\Domains\Auth\Models\Membership;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use Symfony\Component\HttpFoundation\Response;

class ResolveVisibleServiceOrder
{
    public function handle(ServiceOrder $serviceOrder, VisibleServiceOrders $visibleServiceOrders): ServiceOrder
    {
        $membership = currentMembership();

        if ($membership === null) {
            abort(Response::HTTP_FORBIDDEN, 'Missing active membership context.');
        }

        $visible = $visibleServiceOrders->queryFor($membership)
            ->whereKey($serviceOrder->getKey())
            ->first();

        abort_if($visible === null, Response::HTTP_NOT_FOUND);

        return $visible;
    }

    public function membership(): ?Membership
    {
        return currentMembership();
    }
}
