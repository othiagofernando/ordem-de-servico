<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerContact;
use App\Domains\Equipments\Models\Equipment;
use App\Domains\ServiceOrders\Actions\OpenDraftServiceOrder;
use App\Domains\ServiceOrders\Actions\ReserveDraftReference;
use App\Domains\ServiceOrders\Actions\VisibleServiceOrders;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ServiceOrderController extends Controller
{
    /**
     * Create a draft intake record with a temporary visible reference.
     *
     * @group Service Orders
     */
    public function storeDraft(Request $request, ReserveDraftReference $reserveDraftReference): JsonResponse
    {
        $payload = $this->validateDraftPayload($request);

        $serviceOrder = DB::transaction(function () use ($payload, $reserveDraftReference): ServiceOrder {
            $companyId = currentCompany()?->getKey();
            $draftReference = $reserveDraftReference->handle($companyId);

            $serviceOrder = ServiceOrder::query()->create([
                'company_id' => $companyId,
                'customer_id' => $payload['customer_id'] ?? null,
                'customer_contact_id' => $payload['customer_contact_id'] ?? null,
                'equipment_id' => $payload['equipment_id'] ?? null,
                'reference' => $draftReference,
                'draft_reference' => $draftReference,
                'status' => ServiceOrder::STATUS_DRAFT,
                'title' => $payload['reported_problem'] ?? $payload['equipment_description'] ?? 'Draft intake',
                'equipment_description' => $payload['equipment_description'] ?? null,
                'reported_problem' => $payload['reported_problem'] ?? null,
                'intake_checklist' => $payload['intake_checklist'] ?? null,
            ]);

            return $serviceOrder->load(['customer', 'customerContact', 'equipment', 'assignedMembership']);
        });

        return response()->json([
            'data' => $this->serializeServiceOrder($serviceOrder),
        ], Response::HTTP_CREATED);
    }

    /**
     * Update an existing draft intake record so staff can resume and complete it later.
     *
     * @group Service Orders
     */
    public function updateDraft(Request $request, ServiceOrder $serviceOrder): JsonResponse
    {
        $this->ensureServiceOrderBelongsToCurrentCompany($serviceOrder);

        if ($serviceOrder->status !== ServiceOrder::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only draft intake records can be updated through this endpoint.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $payload = $this->validateDraftPayload($request);

        $attributes = [];

        foreach ([
            'customer_id',
            'customer_contact_id',
            'equipment_id',
            'equipment_description',
            'reported_problem',
            'intake_checklist',
        ] as $field) {
            if ($request->exists($field)) {
                $attributes[$field] = $payload[$field] ?? null;
            }
        }

        if ($request->exists('reported_problem') || $request->exists('equipment_description')) {
            $attributes['title'] = $payload['reported_problem'] ?? $payload['equipment_description'] ?? 'Draft intake';
        }

        $serviceOrder->forceFill($attributes)->save();

        return response()->json([
            'data' => $this->serializeServiceOrder($serviceOrder->load(['customer', 'customerContact', 'equipment', 'assignedMembership'])),
        ]);
    }

    /**
     * Formally open a service order once minimum intake data has been completed.
     *
     * @group Service Orders
     */
    public function openDraft(ServiceOrder $serviceOrder, OpenDraftServiceOrder $openDraftServiceOrder): JsonResponse
    {
        $this->ensureServiceOrderBelongsToCurrentCompany($serviceOrder);
        $this->validateOpenableDraft($serviceOrder);

        $opened = $openDraftServiceOrder->handle($serviceOrder);

        return response()->json([
            'data' => $this->serializeServiceOrder($opened),
        ]);
    }

    /**
     * List tenant-scoped service orders visible to the active membership.
     *
     * @group Service Orders
     *
     * Applies the first record-scope rule: technicians only see service orders assigned to them.
     */
    public function index(VisibleServiceOrders $visibleServiceOrders): JsonResponse
    {
        $membership = currentMembership();

        if ($membership === null) {
            return response()->json(['message' => 'Missing active membership context.'], Response::HTTP_FORBIDDEN);
        }

        if (
            ! $membership->hasPermission('service_orders.view_any')
            && ! $membership->hasPermission('service_orders.view_assigned')
        ) {
            return response()->json([
                'message' => 'This membership cannot view service orders.',
            ], Response::HTTP_FORBIDDEN);
        }

        $serviceOrders = $visibleServiceOrders->queryFor($membership)->get();

        return response()->json([
            'data' => $serviceOrders->map(fn (ServiceOrder $serviceOrder): array => $this->serializeServiceOrder($serviceOrder))->all(),
        ]);
    }

    /**
     * Return one visible service order.
     *
     * @group Service Orders
     *
     * Returns 404 when the service order exists in the tenant but is outside the membership record scope.
     */
    public function show(ServiceOrder $serviceOrder, VisibleServiceOrders $visibleServiceOrders): JsonResponse
    {
        $membership = currentMembership();

        if ($membership === null) {
            return response()->json(['message' => 'Missing active membership context.'], Response::HTTP_FORBIDDEN);
        }

        $visible = $visibleServiceOrders->queryFor($membership)
            ->whereKey($serviceOrder->getKey())
            ->first();

        abort_if($visible === null, Response::HTTP_NOT_FOUND);

        return response()->json([
            'data' => $this->serializeServiceOrder($visible->load(['customer', 'customerContact', 'equipment', 'assignedMembership'])),
        ]);
    }

    protected function validateDraftPayload(Request $request): array
    {
        $payload = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'customer_contact_id' => ['nullable', 'integer'],
            'equipment_id' => ['nullable', 'integer'],
            'equipment_description' => ['nullable', 'string'],
            'reported_problem' => ['nullable', 'string'],
            'intake_checklist' => ['nullable', 'array'],
            'intake_checklist.*.label' => ['required_with:intake_checklist', 'string', 'max:255'],
            'intake_checklist.*.checked' => ['nullable', 'boolean'],
            'intake_checklist.*.notes' => ['nullable', 'string'],
        ]);

        $customer = null;

        if (($payload['customer_id'] ?? null) !== null) {
            $customer = Customer::query()->findOrFail($payload['customer_id']);
            abort_unless($customer->company_id === currentCompany()?->getKey(), Response::HTTP_NOT_FOUND);
        }

        if (($payload['customer_contact_id'] ?? null) !== null) {
            $contact = CustomerContact::query()->findOrFail($payload['customer_contact_id']);
            abort_unless($contact->company_id === currentCompany()?->getKey(), Response::HTTP_NOT_FOUND);
            abort_unless($customer !== null && $contact->customer_id === $customer->getKey(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (($payload['equipment_id'] ?? null) !== null) {
            $equipment = Equipment::query()->findOrFail($payload['equipment_id']);
            abort_unless($equipment->company_id === currentCompany()?->getKey(), Response::HTTP_NOT_FOUND);
            abort_unless($customer !== null && $equipment->customer_id === $customer->getKey(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $payload;
    }

    protected function validateOpenableDraft(ServiceOrder $serviceOrder): void
    {
        if ($serviceOrder->status !== ServiceOrder::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft intake records can be opened.',
            ]);
        }

        $validator = validator($serviceOrder->toArray(), [
            'customer_id' => ['required', 'integer'],
            'customer_contact_id' => ['required', 'integer'],
            'equipment_id' => ['required', 'integer'],
            'equipment_description' => ['required', 'string'],
            'reported_problem' => ['required', 'string'],
            'intake_checklist' => ['required', 'array', 'min:1'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeServiceOrder(ServiceOrder $serviceOrder): array
    {
        return [
            'id' => $serviceOrder->getKey(),
            'reference' => $serviceOrder->reference,
            'draft_reference' => $serviceOrder->draft_reference,
            'order_number' => $serviceOrder->order_number,
            'title' => $serviceOrder->title,
            'status' => $serviceOrder->status,
            'customer_id' => $serviceOrder->customer_id,
            'customer_contact_id' => $serviceOrder->customer_contact_id,
            'equipment_id' => $serviceOrder->equipment_id,
            'equipment_description' => $serviceOrder->equipment_description,
            'reported_problem' => $serviceOrder->reported_problem,
            'intake_checklist' => $serviceOrder->intake_checklist,
            'opened_at' => $serviceOrder->opened_at?->toIso8601String(),
            'assigned_membership_id' => $serviceOrder->assigned_membership_id,
        ];
    }

    protected function ensureServiceOrderBelongsToCurrentCompany(ServiceOrder $serviceOrder): void
    {
        abort_unless($serviceOrder->company_id === currentCompany()?->getKey(), Response::HTTP_NOT_FOUND);
    }
}
