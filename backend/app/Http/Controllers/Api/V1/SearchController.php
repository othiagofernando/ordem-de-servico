<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auth\Models\Membership;
use App\Domains\Customers\Models\Customer;
use App\Domains\Equipments\Models\Equipment;
use App\Domains\Learning\Models\Playbook;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $limit   = 4;
        $results = [];

        // Ordens de Serviço
        ServiceOrder::query()
            ->with(['customer', 'equipment'])
            ->where(function ($query) use ($q): void {
                $query
                    ->where('order_number', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('equipment', fn ($e) => $e->where('brand', 'like', "%{$q}%")->orWhere('model', 'like', "%{$q}%"));
            })
            ->latest('opened_at')
            ->limit($limit)
            ->get()
            ->each(function (ServiceOrder $order) use (&$results): void {
                $equipmentLabel = implode(' ', array_filter([$order->equipment?->brand, $order->equipment?->model]));
                $results[] = [
                    'type'     => 'service_order',
                    'id'       => $order->getKey(),
                    'label'    => ($order->order_number ? "OS {$order->order_number}" : "OS #{$order->getKey()}") . ($equipmentLabel ? " · {$equipmentLabel}" : ''),
                    'sublabel' => $order->customer?->name ?? '—',
                    'meta'     => $order->status,
                ];
            });

        // Clientes
        Customer::query()
            ->with('contacts')
            ->where(function ($query) use ($q): void {
                $phone = preg_replace('/\D+/', '', $q);
                $query
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('trade_name', 'like', "%{$q}%")
                    ->orWhere('document', 'like', "%{$q}%")
                    ->orWhereHas('contacts', function ($c) use ($q, $phone): void {
                        $c->where('email', 'like', "%{$q}%")
                            ->orWhere('name', 'like', "%{$q}%");
                        if ($phone !== '') {
                            $c->orWhere('normalized_phone', 'like', "%{$phone}%");
                        }
                    });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->each(function (Customer $customer) use (&$results): void {
                $phone = $customer->contacts->first()?->phone;
                $results[] = [
                    'type'     => 'customer',
                    'id'       => $customer->getKey(),
                    'label'    => $customer->name,
                    'sublabel' => $phone ?? $customer->document ?? '—',
                    'meta'     => $customer->customer_type,
                ];
            });

        // Equipamentos
        Equipment::query()
            ->where(function ($query) use ($q): void {
                $query
                    ->where('brand', 'like', "%{$q}%")
                    ->orWhere('model', 'like', "%{$q}%")
                    ->orWhere('serial_number', 'like', "%{$q}%");
            })
            ->orderBy('brand')
            ->limit($limit)
            ->get()
            ->each(function (Equipment $equipment) use (&$results): void {
                $label = implode(' ', array_filter([$equipment->brand, $equipment->model])) ?: '—';
                $results[] = [
                    'type'     => 'equipment',
                    'id'       => $equipment->getKey(),
                    'label'    => $label,
                    'sublabel' => $equipment->serial_number ?? '—',
                    'meta'     => null,
                ];
            });

        // Usuários (membros da empresa)
        Membership::query()
            ->with('user')
            ->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))
            ->limit($limit)
            ->get()
            ->each(function (Membership $membership) use (&$results): void {
                $results[] = [
                    'type'     => 'member',
                    'id'       => $membership->getKey(),
                    'label'    => $membership->user?->name ?? '—',
                    'sublabel' => $membership->user?->email ?? '—',
                    'meta'     => $membership->role,
                ];
            });

        // Playbooks
        Playbook::query()
            ->where('status', 'published')
            ->where(function ($query) use ($q): void {
                $query->where('title', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%");
            })
            ->orderBy('title')
            ->limit($limit)
            ->get()
            ->each(function (Playbook $playbook) use (&$results): void {
                $results[] = [
                    'type'     => 'playbook',
                    'id'       => $playbook->getKey(),
                    'label'    => $playbook->title,
                    'sublabel' => $playbook->category ?? 'Geral',
                    'meta'     => null,
                ];
            });

        return response()->json(['data' => $results]);
    }
}
