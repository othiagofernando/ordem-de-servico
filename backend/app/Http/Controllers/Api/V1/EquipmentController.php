<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Customers\Models\Customer;
use App\Domains\Equipments\Actions\HandleSerialNumberConstraint;
use App\Domains\Equipments\Models\Equipment;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EquipmentController extends Controller
{
    /**
     * List or search tenant-scoped equipment records.
     *
     * @group Equipments
     *
     * Searches by category, brand, model, serial number, and linked customer name within the active company.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        $perPage = min((int) $request->query('per_page', 15), 100);

        $equipments = Equipment::query()
            ->with('customer')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('category', 'like', '%'.$search.'%')
                        ->orWhere('brand', 'like', '%'.$search.'%')
                        ->orWhere('model', 'like', '%'.$search.'%')
                        ->orWhere('serial_number', 'like', '%'.$search.'%')
                        ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($equipments->items())->map(fn (Equipment $equipment): array => $this->serializeEquipment($equipment))->all(),
            'meta' => [
                'total'        => $equipments->total(),
                'per_page'     => $equipments->perPage(),
                'current_page' => $equipments->currentPage(),
                'last_page'    => $equipments->lastPage(),
                'from'         => $equipments->firstItem() ?? 0,
                'to'           => $equipments->lastItem() ?? 0,
            ],
        ]);
    }

    /**
     * Create a tenant-scoped equipment record.
     *
     * @group Equipments
     *
     * Creates a persistent customer-linked equipment record and enforces per-company serial uniqueness unless a privileged override is used.
     */
    public function store(Request $request, HandleSerialNumberConstraint $handleSerialNumberConstraint): JsonResponse
    {
        $payload = $this->validatePayload($request);

        $customer = $this->resolveCustomer($payload['customer_id']);

        $handleSerialNumberConstraint->ensureCanUse(
            serialNumber: $payload['serial_number'] ?? null,
            overrideRequested: $payload['override_serial_uniqueness'] ?? false,
            overrideReason: $payload['override_reason'] ?? null,
        );

        $equipment = DB::transaction(function () use ($payload, $customer, $handleSerialNumberConstraint): Equipment {
            $equipment = Equipment::query()->create([
                'company_id' => currentCompany()?->getKey(),
                'customer_id' => $customer->getKey(),
                'category' => $payload['category'],
                'brand' => $payload['brand'] ?? null,
                'model' => $payload['model'] ?? null,
                'serial_number' => $payload['serial_number'] ?? null,
                'accessories' => $payload['accessories'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ]);

            if (($payload['override_serial_uniqueness'] ?? false) && ! empty($payload['serial_number'])) {
                $handleSerialNumberConstraint->logOverride($equipment, $payload['serial_number'], $payload['override_reason']);
            }

            return $equipment->load(['customer', 'serialOverrides']);
        });

        return response()->json([
            'data' => $this->serializeEquipment($equipment),
        ], Response::HTTP_CREATED);
    }

    /**
     * Return one tenant-scoped equipment record.
     *
     * @group Equipments
     */
    public function show(Equipment $equipment): JsonResponse
    {
        $this->ensureEquipmentBelongsToCurrentCompany($equipment);

        return response()->json([
            'data' => $this->serializeEquipment($equipment->load(['customer', 'serialOverrides'])),
        ]);
    }

    /**
     * Update a tenant-scoped equipment record.
     *
     * @group Equipments
     *
     * Updates persistent equipment data while preserving serial uniqueness and the privileged override audit path.
     */
    public function update(
        Request $request,
        Equipment $equipment,
        HandleSerialNumberConstraint $handleSerialNumberConstraint,
    ): JsonResponse {
        $this->ensureEquipmentBelongsToCurrentCompany($equipment);

        $payload = $this->validatePayload($request, $equipment);
        $customer = $this->resolveCustomer($payload['customer_id']);

        $handleSerialNumberConstraint->ensureCanUse(
            serialNumber: $payload['serial_number'] ?? null,
            ignoreEquipment: $equipment,
            overrideRequested: $payload['override_serial_uniqueness'] ?? false,
            overrideReason: $payload['override_reason'] ?? null,
        );

        $equipment = DB::transaction(function () use ($equipment, $payload, $customer, $handleSerialNumberConstraint): Equipment {
            $equipment->forceFill([
                'customer_id' => $customer->getKey(),
                'category' => $payload['category'],
                'brand' => $payload['brand'] ?? null,
                'model' => $payload['model'] ?? null,
                'serial_number' => $payload['serial_number'] ?? null,
                'accessories' => $payload['accessories'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ])->save();

            if (($payload['override_serial_uniqueness'] ?? false) && ! empty($payload['serial_number'])) {
                $handleSerialNumberConstraint->logOverride($equipment, $payload['serial_number'], $payload['override_reason']);
            }

            return $equipment->load(['customer', 'serialOverrides']);
        });

        return response()->json([
            'data' => $this->serializeEquipment($equipment),
        ]);
    }

    protected function validatePayload(Request $request, ?Equipment $equipment = null): array
    {
        return $request->validate([
            'customer_id' => ['required', 'integer'],
            'category' => ['required', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'accessories' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'override_serial_uniqueness' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ]);
    }

    protected function resolveCustomer(int $customerId): Customer
    {
        $customer = Customer::query()->withoutGlobalScopes()->findOrFail($customerId);

        if ($customer->company_id !== currentCompany()?->getKey()) {
            throw ValidationException::withMessages([
                'customer_id' => 'The selected customer is invalid for the active company.',
            ]);
        }

        return $customer;
    }

    protected function ensureEquipmentBelongsToCurrentCompany(Equipment $equipment): void
    {
        abort_unless($equipment->company_id === currentCompany()?->getKey(), Response::HTTP_NOT_FOUND);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeEquipment(Equipment $equipment): array
    {
        return [
            'id' => $equipment->getKey(),
            'customer_id' => $equipment->customer_id,
            'customer_name' => $equipment->customer?->name,
            'category' => $equipment->category,
            'brand' => $equipment->brand,
            'model' => $equipment->model,
            'serial_number' => $equipment->serial_number,
            'accessories' => $equipment->accessories,
            'notes' => $equipment->notes,
            'serial_overrides' => $equipment->relationLoaded('serialOverrides')
                ? $equipment->serialOverrides->map(fn ($override): array => [
                    'id' => $override->getKey(),
                    'serial_number' => $override->serial_number,
                    'reason' => $override->reason,
                    'membership_id' => $override->membership_id,
                ])->all()
                : [],
        ];
    }
}
