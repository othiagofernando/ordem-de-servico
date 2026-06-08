<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Customers\Actions\FindLikelyDuplicateCustomers;
use App\Domains\Customers\Actions\SyncCustomerAddresses;
use App\Domains\Customers\Actions\SyncCustomerContacts;
use App\Domains\Customers\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class CustomerController extends Controller
{
    /**
     * List or search tenant-scoped customers.
     *
     * @group Customers
     *
     * Searches by customer name, document, contact e-mail, and contact phone within the active company.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->with(['contacts', 'addresses'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('trade_name', 'like', '%'.$search.'%')
                        ->orWhere('document', 'like', '%'.$search.'%')
                        ->orWhereHas('contacts', function ($contactQuery) use ($search): void {
                            $contactQuery
                                ->where('email', 'like', '%'.$search.'%')
                                ->orWhere('phone', 'like', '%'.$search.'%')
                                ->orWhere('normalized_phone', 'like', '%'.preg_replace('/\D+/', '', $search).'%');
                        });
                });
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $customers->map(fn (Customer $customer): array => $this->serializeCustomer($customer))->all(),
        ]);
    }

    /**
     * Create a tenant-scoped customer.
     *
     * @group Customers
     *
     * Creates a customer with multiple contacts and addresses, enforcing per-company document uniqueness and duplicate warnings.
     */
    public function store(
        Request $request,
        FindLikelyDuplicateCustomers $findLikelyDuplicateCustomers,
        SyncCustomerContacts $syncCustomerContacts,
        SyncCustomerAddresses $syncCustomerAddresses,
    ): JsonResponse {
        $payload = $this->validatePayload($request);

        $duplicates = $findLikelyDuplicateCustomers->handle($payload['contacts']);

        if ($duplicates->isNotEmpty() && ! ($payload['confirm_duplicates'] ?? false)) {
            return $this->duplicateWarningResponse($duplicates);
        }

        $customer = DB::transaction(function () use ($payload, $syncCustomerContacts, $syncCustomerAddresses): Customer {
            $customer = Customer::query()->create([
                'company_id' => currentCompany()?->getKey(),
                'customer_type' => $payload['customer_type'],
                'name' => $payload['name'],
                'trade_name' => $payload['trade_name'] ?? null,
                'document' => $payload['document'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ]);

            $syncCustomerContacts->handle($customer, $payload['contacts']);
            $syncCustomerAddresses->handle($customer, $payload['addresses'] ?? []);

            return $customer->load(['contacts', 'addresses']);
        });

        return response()->json([
            'data' => $this->serializeCustomer($customer),
        ], Response::HTTP_CREATED);
    }

    /**
     * Return one tenant-scoped customer.
     *
     * @group Customers
     */
    public function show(Customer $customer): JsonResponse
    {
        $this->ensureCustomerBelongsToCurrentCompany($customer);

        return response()->json([
            'data' => $this->serializeCustomer($customer->load(['contacts', 'addresses'])),
        ]);
    }

    /**
     * Update a tenant-scoped customer.
     *
     * @group Customers
     *
     * Updates customer fields and requires explicit confirmation when likely duplicates are detected.
     */
    public function update(
        Request $request,
        Customer $customer,
        FindLikelyDuplicateCustomers $findLikelyDuplicateCustomers,
        SyncCustomerContacts $syncCustomerContacts,
        SyncCustomerAddresses $syncCustomerAddresses,
    ): JsonResponse {
        $this->ensureCustomerBelongsToCurrentCompany($customer);

        $payload = $this->validatePayload($request, $customer);

        $duplicates = $findLikelyDuplicateCustomers->handle($payload['contacts'], $customer->getKey());

        if ($duplicates->isNotEmpty() && ! ($payload['confirm_duplicates'] ?? false)) {
            return $this->duplicateWarningResponse($duplicates);
        }

        $customer = DB::transaction(function () use ($customer, $payload, $syncCustomerContacts, $syncCustomerAddresses): Customer {
            $customer->forceFill([
                'customer_type' => $payload['customer_type'],
                'name' => $payload['name'],
                'trade_name' => $payload['trade_name'] ?? null,
                'document' => $payload['document'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ])->save();

            $syncCustomerContacts->handle($customer, $payload['contacts']);
            $syncCustomerAddresses->handle($customer, $payload['addresses'] ?? []);

            return $customer->load(['contacts', 'addresses']);
        });

        return response()->json([
            'data' => $this->serializeCustomer($customer),
        ]);
    }

    protected function validatePayload(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'customer_type' => ['required', 'string', Rule::in(['pf', 'pj'])],
            'name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'document' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('customers', 'document')
                    ->where(fn ($query) => $query->where('company_id', currentCompany()?->getKey()))
                    ->ignore($customer?->getKey()),
            ],
            'notes' => ['nullable', 'string'],
            'confirm_duplicates' => ['sometimes', 'boolean'],
            'contacts' => ['required', 'array', 'min:1'],
            'contacts.*.label' => ['nullable', 'string', 'max:100'],
            'contacts.*.name' => ['nullable', 'string', 'max:255'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:30'],
            'addresses' => ['sometimes', 'array'],
            'addresses.*.label' => ['nullable', 'string', 'max:100'],
            'addresses.*.recipient' => ['nullable', 'string', 'max:255'],
            'addresses.*.line_1' => ['required_with:addresses', 'string', 'max:255'],
            'addresses.*.line_2' => ['nullable', 'string', 'max:255'],
            'addresses.*.district' => ['nullable', 'string', 'max:150'],
            'addresses.*.city' => ['nullable', 'string', 'max:150'],
            'addresses.*.state' => ['nullable', 'string', 'max:150'],
            'addresses.*.postal_code' => ['nullable', 'string', 'max:20'],
        ]);
    }

    /**
     * @param  Collection<int, Customer>  $duplicates
     */
    protected function duplicateWarningResponse($duplicates): JsonResponse
    {
        return response()->json([
            'message' => 'Likely duplicate customers were found. Resubmit with confirm_duplicates=true to continue.',
            'duplicate_candidates' => $duplicates->map(fn (Customer $customer): array => [
                'id' => $customer->getKey(),
                'name' => $customer->name,
                'document' => $customer->document,
                'contacts' => $customer->contacts->map(fn ($contact): array => [
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                ])->all(),
            ])->all(),
        ], Response::HTTP_CONFLICT);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->getKey(),
            'customer_type' => $customer->customer_type,
            'name' => $customer->name,
            'trade_name' => $customer->trade_name,
            'document' => $customer->document,
            'notes' => $customer->notes,
            'contacts' => $customer->contacts->map(fn ($contact): array => [
                'id' => $contact->getKey(),
                'label' => $contact->label,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
            ])->all(),
            'addresses' => $customer->addresses->map(fn ($address): array => [
                'id' => $address->getKey(),
                'label' => $address->label,
                'recipient' => $address->recipient,
                'line_1' => $address->line_1,
                'line_2' => $address->line_2,
                'district' => $address->district,
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
            ])->all(),
        ];
    }

    protected function ensureCustomerBelongsToCurrentCompany(Customer $customer): void
    {
        abort_unless($customer->company_id === currentCompany()?->getKey(), Response::HTTP_NOT_FOUND);
    }
}
