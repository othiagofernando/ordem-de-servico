<?php

namespace App\Domains\Customers\Actions;

use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Support\NormalizeCustomerContact;
use Illuminate\Support\Collection;

class FindLikelyDuplicateCustomers
{
    public function __construct(
        protected NormalizeCustomerContact $normalizeCustomerContact,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $contacts
     * @return Collection<int, Customer>
     */
    public function handle(array $contacts, ?int $ignoreCustomerId = null): Collection
    {
        $normalizedEmails = collect($contacts)
            ->map(fn (array $contact): ?string => $this->normalizeCustomerContact->email($contact['email'] ?? null))
            ->filter()
            ->values();

        $normalizedPhones = collect($contacts)
            ->map(fn (array $contact): ?string => $this->normalizeCustomerContact->phone($contact['phone'] ?? null))
            ->filter()
            ->values();

        if ($normalizedEmails->isEmpty() && $normalizedPhones->isEmpty()) {
            return collect();
        }

        return Customer::query()
            ->with('contacts')
            ->when($ignoreCustomerId !== null, fn ($query) => $query->whereKeyNot($ignoreCustomerId))
            ->whereHas('contacts', function ($query) use ($normalizedEmails, $normalizedPhones): void {
                $query->when(
                    $normalizedEmails->isNotEmpty(),
                    fn ($inner) => $inner->whereIn('normalized_email', $normalizedEmails->all()),
                )->when(
                    $normalizedPhones->isNotEmpty(),
                    fn ($inner) => $inner->orWhereIn('normalized_phone', $normalizedPhones->all()),
                );
            })
            ->get();
    }
}
