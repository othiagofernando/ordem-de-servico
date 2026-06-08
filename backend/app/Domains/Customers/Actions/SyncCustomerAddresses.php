<?php

namespace App\Domains\Customers\Actions;

use App\Domains\Customers\Models\Customer;

class SyncCustomerAddresses
{
    /**
     * @param  array<int, array<string, mixed>>  $addresses
     */
    public function handle(Customer $customer, array $addresses): void
    {
        $customer->addresses()->delete();

        foreach ($addresses as $address) {
            $customer->addresses()->create([
                'company_id' => $customer->company_id,
                'label' => $address['label'] ?? null,
                'recipient' => $address['recipient'] ?? null,
                'line_1' => $address['line_1'] ?? null,
                'line_2' => $address['line_2'] ?? null,
                'district' => $address['district'] ?? null,
                'city' => $address['city'] ?? null,
                'state' => $address['state'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
            ]);
        }
    }
}
