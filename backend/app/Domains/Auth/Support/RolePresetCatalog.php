<?php

namespace App\Domains\Auth\Support;

class RolePresetCatalog
{
    /**
     * @return array<string, array<int, string>>
     */
    public function all(): array
    {
        return [
            'administrator' => [
                'memberships.manage',
                'customers.view_any',
                'customers.manage',
                'equipments.view_any',
                'equipments.manage',
                'equipments.override_serial_uniqueness',
                'service_orders.view_any',
                'service_orders.view_assigned',
                'service_orders.manage',
                'service_orders.assign',
                'service_orders.diagnose',
                'service_orders.execute',
                'service_orders.finance',
            ],
            'manager' => [
                'customers.view_any',
                'customers.manage',
                'equipments.view_any',
                'equipments.manage',
                'equipments.override_serial_uniqueness',
                'service_orders.view_any',
                'service_orders.view_assigned',
                'service_orders.manage',
                'service_orders.assign',
                'service_orders.diagnose',
                'service_orders.execute',
                'service_orders.finance',
            ],
            'attendant' => [
                'customers.view_any',
                'customers.manage',
                'equipments.view_any',
                'equipments.manage',
                'service_orders.view_any',
                'service_orders.manage',
            ],
            'technician' => [
                'service_orders.view_assigned',
                'service_orders.diagnose',
                'service_orders.execute',
            ],
            'finance' => [
                'service_orders.view_any',
                'service_orders.finance',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function permissionsFor(string $rolePreset): array
    {
        return $this->all()[$rolePreset] ?? [];
    }
}
