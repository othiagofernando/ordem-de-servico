<?php

namespace App\Domains\ServiceOrders\Actions;

use App\Domains\Auth\Models\Membership;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use Symfony\Component\HttpFoundation\Response;

class ResolveFinanceVisibleServiceOrder
{
    public function handle(
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        ResolveVisibleServiceOrder $resolveVisibleServiceOrder,
    ): ServiceOrder {
        $membership = currentMembership();

        if ($membership === null) {
            abort(Response::HTTP_FORBIDDEN, 'Missing active membership context.');
        }

        if (! $membership->hasPermission('service_orders.finance')) {
            abort(Response::HTTP_FORBIDDEN, 'This membership cannot print payment receipts.');
        }

        return $resolveVisibleServiceOrder->handle($serviceOrder, $visibleServiceOrders);
    }

    public function membership(): ?Membership
    {
        return currentMembership();
    }
}
