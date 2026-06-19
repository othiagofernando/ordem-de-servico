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
                'company_settings.manage',
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
                'service_orders.override_delivery_balance',
                'parts.view_any',
                'parts.manage',
                'ratings.view_any',
            ],
            'manager' => [
                'company_settings.manage',
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
                'service_orders.override_delivery_balance',
                'parts.view_any',
                'parts.manage',
                'ratings.view_any',
            ],
            'attendant' => [
                'customers.view_any',
                'customers.manage',
                'equipments.view_any',
                'equipments.manage',
                'service_orders.view_any',
                'service_orders.manage',
                'parts.view_any',
            ],
            'technician' => [
                'service_orders.view_assigned',
                'service_orders.diagnose',
                'service_orders.execute',
                'parts.view_any',
                'parts.manage',
            ],
            'finance' => [
                'service_orders.view_any',
                'service_orders.finance',
                'parts.view_any',
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
