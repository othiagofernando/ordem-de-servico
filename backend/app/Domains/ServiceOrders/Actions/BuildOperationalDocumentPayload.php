<?php

namespace App\Domains\ServiceOrders\Actions;

use App\Domains\ServiceOrders\Models\QuoteRevision;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderPayment;

class BuildOperationalDocumentPayload
{
    /**
     * @return array<string, mixed>
     */
    public function quote(ServiceOrder $serviceOrder, QuoteRevision $quote): array
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
    public function receipt(ServiceOrder $serviceOrder, ServiceOrderPayment $payment): array
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
    public function delivery(ServiceOrder $serviceOrder): array
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
