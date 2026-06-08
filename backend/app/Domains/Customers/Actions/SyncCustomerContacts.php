<?php

namespace App\Domains\Customers\Actions;

use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Support\NormalizeCustomerContact;

class SyncCustomerContacts
{
    public function __construct(
        protected NormalizeCustomerContact $normalizeCustomerContact,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $contacts
     */
    public function handle(Customer $customer, array $contacts): void
    {
        $customer->contacts()->delete();

        foreach ($contacts as $contact) {
            $customer->contacts()->create([
                'company_id' => $customer->company_id,
                'label' => $contact['label'] ?? null,
                'name' => $contact['name'] ?? null,
                'email' => $contact['email'] ?? null,
                'phone' => $contact['phone'] ?? null,
                'normalized_email' => $this->normalizeCustomerContact->email($contact['email'] ?? null),
                'normalized_phone' => $this->normalizeCustomerContact->phone($contact['phone'] ?? null),
            ]);
        }
    }
}
