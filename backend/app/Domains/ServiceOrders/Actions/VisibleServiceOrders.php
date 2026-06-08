<?php

namespace App\Domains\ServiceOrders\Actions;

use App\Domains\Auth\Models\Membership;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Builder;

class VisibleServiceOrders
{
    public function queryFor(Membership $membership): Builder
    {
        $query = ServiceOrder::query()->orderBy('id');

        if ($membership->hasPermission('service_orders.view_any')) {
            return $query;
        }

        if ($membership->hasPermission('service_orders.view_assigned')) {
            return $query->where('assigned_membership_id', $membership->getKey());
        }

        return $query->whereRaw('1 = 0');
    }
}
