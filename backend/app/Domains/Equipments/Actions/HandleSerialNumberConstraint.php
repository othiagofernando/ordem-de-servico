<?php

namespace App\Domains\Equipments\Actions;

use App\Domains\Equipments\Models\Equipment;
use App\Domains\Equipments\Models\EquipmentSerialOverride;
use Illuminate\Validation\ValidationException;

class HandleSerialNumberConstraint
{
    public function ensureCanUse(?string $serialNumber, ?Equipment $ignoreEquipment = null, bool $overrideRequested = false, ?string $overrideReason = null): void
    {
        if ($serialNumber === null || trim($serialNumber) === '') {
            return;
        }

        $duplicate = Equipment::query()
            ->when($ignoreEquipment !== null, fn ($query) => $query->whereKeyNot($ignoreEquipment->getKey()))
            ->where('serial_number', $serialNumber)
            ->first();

        if ($duplicate === null) {
            return;
        }

        if (! $overrideRequested || currentMembership() === null || ! currentMembership()->hasPermission('equipments.override_serial_uniqueness')) {
            throw ValidationException::withMessages([
                'serial_number' => 'The serial number has already been taken for this company.',
            ]);
        }

        if ($overrideReason === null || trim($overrideReason) === '') {
            throw ValidationException::withMessages([
                'override_reason' => 'An override reason is required when reusing a serial number.',
            ]);
        }
    }

    public function logOverride(Equipment $equipment, string $serialNumber, string $reason): void
    {
        EquipmentSerialOverride::query()->create([
            'company_id' => $equipment->company_id,
            'equipment_id' => $equipment->getKey(),
            'membership_id' => currentMembership()?->getKey(),
            'serial_number' => $serialNumber,
            'reason' => $reason,
        ]);
    }
}
