<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\ServiceOrders\Actions\NormalizeQuoteRevisionStates;
use App\Domains\ServiceOrders\Actions\VisibleServiceOrders;
use App\Domains\ServiceOrders\Models\QuoteRevision;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderPayment;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ServiceOrderDocumentPrintController extends Controller
{
    /**
     * Return a printable payload for the current quote revision of a visible service order.
     *
     * @group Service Orders
     */
    public function quote(
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        NormalizeQuoteRevisionStates $normalizeQuoteRevisionStates,
    ): JsonResponse {
        $visible = $this->resolveVisibleServiceOrder($serviceOrder, $visibleServiceOrders);
        $visible->loadMissing(['company', 'customer', 'customerContact', 'equipment']);

        $currentQuote = $normalizeQuoteRevisionStates->handle($visible)
            ->sortByDesc('revision_number')
            ->first();

        if ($currentQuote === null) {
            return response()->json([
                'message' => 'This service order has no quote to print.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => $this->serializeQuoteDocument($visible, $currentQuote),
        ]);
    }

    /**
     * Return a printable payload for one immutable payment receipt snapshot.
     *
     * @group Service Orders
     */
    public function receipt(
        ServiceOrder $serviceOrder,
        ServiceOrderPayment $payment,
        VisibleServiceOrders $visibleServiceOrders,
    ): JsonResponse {
        $visible = $this->resolveFinanceVisibleServiceOrder($serviceOrder, $visibleServiceOrders);
        $visible->loadMissing(['company', 'customer', 'customerContact']);

        abort_if(
            $payment->service_order_id !== $visible->getKey() || $payment->company_id !== $visible->company_id,
            Response::HTTP_NOT_FOUND,
        );

        return response()->json([
            'data' => $this->serializeReceiptDocument($visible, $payment),
        ]);
    }

    /**
     * Return a printable payload for one delivered service order handoff term.
     *
     * @group Service Orders
     */
    public function delivery(
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
    ): JsonResponse {
        $visible = $this->resolveVisibleServiceOrder($serviceOrder, $visibleServiceOrders);
        $visible->loadMissing(['company', 'customer', 'customerContact', 'equipment', 'charges', 'payments']);

        if ($visible->status !== ServiceOrder::STATUS_DELIVERED || $visible->delivered_at === null) {
            return response()->json([
                'message' => 'Only delivered service orders can expose a printable delivery term.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'data' => $this->serializeDeliveryDocument($visible),
        ]);
    }

    protected function resolveVisibleServiceOrder(
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
    ): ServiceOrder {
        $membership = currentMembership();

        if ($membership === null) {
            abort(Response::HTTP_FORBIDDEN, 'Missing active membership context.');
        }

        $visible = $visibleServiceOrders->queryFor($membership)
            ->whereKey($serviceOrder->getKey())
            ->first();

        abort_if($visible === null, Response::HTTP_NOT_FOUND);

        return $visible;
    }

    protected function resolveFinanceVisibleServiceOrder(
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
    ): ServiceOrder {
        $membership = currentMembership();

        if ($membership === null) {
            abort(Response::HTTP_FORBIDDEN, 'Missing active membership context.');
        }

        if (! $membership->hasPermission('service_orders.finance')) {
            abort(Response::HTTP_FORBIDDEN, 'This membership cannot print payment receipts.');
        }

        return $this->resolveVisibleServiceOrder($serviceOrder, $visibleServiceOrders);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeQuoteDocument(ServiceOrder $serviceOrder, QuoteRevision $quote): array
    {
        return [
            'document_type' => 'quote',
            'generated_at' => now()->toIso8601String(),
            'company' => $this->serializeCompany($serviceOrder),
            'service_order' => $this->serializeServiceOrderSummary($serviceOrder),
            'customer' => $this->serializeCustomerSummary($serviceOrder),
            'quote' => [
                'id' => $quote->getKey(),
                'revision_number' => $quote->revision_number,
                'status' => $quote->status,
                'currency' => $quote->currency,
                'total_cents' => $quote->total_cents,
                'notes' => $quote->notes,
                'valid_until' => $quote->valid_until?->toIso8601String(),
                'submitted_at' => $quote->submitted_at?->toIso8601String(),
                'line_items' => collect($quote->line_items ?? [])->map(fn (array $item): array => [
                    'description' => $item['description'] ?? '',
                    'quantity' => (int) ($item['quantity'] ?? 0),
                    'unit_price_cents' => (int) ($item['unit_price_cents'] ?? 0),
                    'line_total_cents' => (int) ($item['line_total_cents'] ?? 0),
                ])->values()->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeReceiptDocument(ServiceOrder $serviceOrder, ServiceOrderPayment $payment): array
    {
        $snapshot = collect($payment->receipt_snapshot?->getArrayCopy() ?? $payment->receipt_snapshot ?? []);

        return [
            'document_type' => 'receipt',
            'generated_at' => now()->toIso8601String(),
            'company' => $this->serializeCompany($serviceOrder),
            'service_order' => $this->serializeServiceOrderSummary($serviceOrder),
            'customer' => [
                'name' => $snapshot->get('customer_name') ?: $serviceOrder->customer?->name,
                'document' => $serviceOrder->customer?->document,
                'contact_name' => $serviceOrder->customerContact?->name,
                'contact_phone' => $serviceOrder->customerContact?->phone,
            ],
            'receipt' => [
                'payment_id' => $payment->getKey(),
                'amount_cents' => $payment->amount_cents,
                'currency' => $payment->currency,
                'payment_method' => $payment->payment_method,
                'received_at' => $payment->received_at?->toIso8601String(),
                'received_by_membership_id' => $payment->received_by_membership_id,
                'notes' => $payment->notes,
                'outstanding_balance_before_payment_cents' => (int) ($snapshot->get('outstanding_balance_before_payment_cents') ?? 0),
                'balance_after_payment_cents' => (int) ($snapshot->get('balance_after_payment_cents') ?? 0),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeDeliveryDocument(ServiceOrder $serviceOrder): array
    {
        $outstandingBalanceCents = (int) $serviceOrder->charges->sum('amount_cents') - (int) $serviceOrder->payments->sum('amount_cents');

        return [
            'document_type' => 'delivery_term',
            'generated_at' => now()->toIso8601String(),
            'company' => $this->serializeCompany($serviceOrder),
            'service_order' => array_merge($this->serializeServiceOrderSummary($serviceOrder), [
                'delivery_notes' => $serviceOrder->delivery_notes,
                'received_by_name' => $serviceOrder->received_by_name,
                'delivered_at' => $serviceOrder->delivered_at?->toIso8601String(),
                'outstanding_balance_cents' => $outstandingBalanceCents,
            ]),
            'customer' => $this->serializeCustomerSummary($serviceOrder),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeCompany(ServiceOrder $serviceOrder): array
    {
        return [
            'name' => $serviceOrder->company?->name,
            'document' => $serviceOrder->company?->document,
            'email' => $serviceOrder->company?->email,
            'phone' => $serviceOrder->company?->phone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeServiceOrderSummary(ServiceOrder $serviceOrder): array
    {
        return [
            'id' => $serviceOrder->getKey(),
            'reference' => $serviceOrder->reference,
            'order_number' => $serviceOrder->order_number,
            'status' => $serviceOrder->status,
            'opened_at' => $serviceOrder->opened_at?->toIso8601String(),
            'reported_problem' => $serviceOrder->reported_problem,
            'equipment_description' => $serviceOrder->equipment_description,
            'equipment_brand' => $serviceOrder->equipment?->brand,
            'equipment_model' => $serviceOrder->equipment?->model,
            'equipment_serial_number' => $serviceOrder->equipment?->serial_number,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeCustomerSummary(ServiceOrder $serviceOrder): array
    {
        return [
            'name' => $serviceOrder->customer?->name,
            'document' => $serviceOrder->customer?->document,
            'contact_name' => $serviceOrder->customerContact?->name,
            'contact_phone' => $serviceOrder->customerContact?->phone,
        ];
    }
}
