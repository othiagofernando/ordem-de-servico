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
            ],
            'finance' => [],
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
