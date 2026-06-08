<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\ServiceOrders\Actions\RecordServiceOrderTimelineEvent;
use App\Domains\ServiceOrders\Actions\VisibleServiceOrders;
use App\Domains\ServiceOrders\Models\QuoteRevision;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderCharge;
use App\Domains\ServiceOrders\Models\ServiceOrderPayment;
use App\Domains\ServiceOrders\Models\ServiceOrderTimelineEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ServiceOrderFinancialController extends Controller
{
    /**
     * View the financial summary for a visible service order.
     *
     * @group Service Orders
     */
    public function show(ServiceOrder $serviceOrder, VisibleServiceOrders $visibleServiceOrders): JsonResponse
    {
        $visible = $this->resolveFinanceableServiceOrder($serviceOrder, $visibleServiceOrders);

        return response()->json([
            'data' => $this->serializeFinancials($visible->fresh(['charges', 'payments'])),
        ]);
    }

    /**
     * Derive the main charge from the latest approved quote.
     *
     * @group Service Orders
     */
    public function deriveApprovedQuoteCharge(
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
    ): JsonResponse {
        $visible = $this->resolveFinanceableServiceOrder($serviceOrder, $visibleServiceOrders);
        $membership = currentMembership();

        $approvedQuote = $visible->quoteRevisions()
            ->where('status', QuoteRevision::STATUS_APPROVED)
            ->orderByDesc('decisioned_at')
            ->orderByDesc('id')
            ->first();

        if ($approvedQuote === null) {
            throw ValidationException::withMessages([
                'quote_revision' => 'An approved quote is required before charges can be derived.',
            ]);
        }

        if ($visible->charges()->where('charge_type', ServiceOrderCharge::TYPE_APPROVED_QUOTE)->exists()) {
            return response()->json([
                'message' => 'Charges have already been derived from the approved quote.',
            ], Response::HTTP_CONFLICT);
        }

        DB::transaction(function () use ($visible, $approvedQuote, $membership, $recordTimelineEvent): void {
            ServiceOrderCharge::query()->create([
                'company_id' => $visible->company_id,
                'service_order_id' => $visible->getKey(),
                'source_quote_revision_id' => $approvedQuote->getKey(),
                'created_by_membership_id' => $membership?->getKey(),
                'charge_type' => ServiceOrderCharge::TYPE_APPROVED_QUOTE,
                'description' => sprintf('Approved quote revision #%d', $approvedQuote->revision_number),
                'amount_cents' => $approvedQuote->total_cents,
                'currency' => $approvedQuote->currency,
                'metadata' => [
                    'quote_revision_id' => $approvedQuote->getKey(),
                    'revision_number' => $approvedQuote->revision_number,
                ],
            ]);

            $recordTimelineEvent->handle(
                $visible,
                type: 'financial_charge_derived_from_quote',
                title: 'Financial charge derived from approved quote',
                visibility: ServiceOrderTimelineEvent::VISIBILITY_INTERNAL,
                actorMembership: $membership,
                payload: [
                    'quote_revision_id' => $approvedQuote->getKey(),
                    'amount_cents' => $approvedQuote->total_cents,
                    'currency' => $approvedQuote->currency,
                ],
            );
        });

        return response()->json([
            'data' => $this->serializeFinancials($visible->fresh(['charges', 'payments'])),
        ], Response::HTTP_CREATED);
    }

    /**
     * Record a diagnostic fee charge independent of repair approval.
     *
     * @group Service Orders
     */
    public function addDiagnosticFee(
        Request $request,
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
    ): JsonResponse {
        return $this->storeCharge(
            $request,
            $serviceOrder,
            $visibleServiceOrders,
            $recordTimelineEvent,
            ServiceOrderCharge::TYPE_DIAGNOSTIC_FEE,
            'financial_diagnostic_fee_recorded',
            'Diagnostic fee recorded',
        );
    }

    /**
     * Record a controlled financial adjustment.
     *
     * @group Service Orders
     */
    public function addAdjustment(
        Request $request,
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
    ): JsonResponse {
        return $this->storeCharge(
            $request,
            $serviceOrder,
            $visibleServiceOrders,
            $recordTimelineEvent,
            ServiceOrderCharge::TYPE_ADJUSTMENT,
            'financial_adjustment_recorded',
            'Financial adjustment recorded',
            allowNegative: true,
        );
    }

    /**
     * Record a payment event and immutable receipt snapshot.
     *
     * @group Service Orders
     */
    public function recordPayment(
        Request $request,
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
    ): JsonResponse {
        $visible = $this->resolveFinanceableServiceOrder($serviceOrder, $visibleServiceOrders);
        $membership = currentMembership();
        $payload = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'in:cash,pix,credit_card,debit_card,bank_transfer,other'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ]);

        $financials = $this->summarizeFinancials($visible->loadMissing(['charges', 'payments']));

        if ($financials['outstanding_balance_cents'] <= 0) {
            throw ValidationException::withMessages([
                'amount_cents' => 'There is no outstanding balance to receive.',
            ]);
        }

        if ($payload['amount_cents'] > $financials['outstanding_balance_cents']) {
            throw ValidationException::withMessages([
                'amount_cents' => 'Payment amount cannot exceed the outstanding balance.',
            ]);
        }

        DB::transaction(function () use ($visible, $membership, $payload, $financials, $recordTimelineEvent): void {
            $receivedAt = now();
            $balanceAfterPayment = $financials['outstanding_balance_cents'] - $payload['amount_cents'];

            ServiceOrderPayment::query()->create([
                'company_id' => $visible->company_id,
                'service_order_id' => $visible->getKey(),
                'received_by_membership_id' => $membership?->getKey(),
                'amount_cents' => $payload['amount_cents'],
                'currency' => strtoupper((string) ($payload['currency'] ?? 'BRL')),
                'payment_method' => $payload['payment_method'],
                'notes' => $payload['notes'] ?? null,
                'received_at' => $receivedAt,
                'receipt_snapshot' => [
                    'service_order_id' => $visible->getKey(),
                    'service_order_reference' => $visible->reference,
                    'service_order_status' => $visible->status,
                    'customer_name' => $visible->customer?->name,
                    'amount_cents' => $payload['amount_cents'],
                    'currency' => strtoupper((string) ($payload['currency'] ?? 'BRL')),
                    'payment_method' => $payload['payment_method'],
                    'received_at' => $receivedAt->toIso8601String(),
                    'received_by_membership_id' => $membership?->getKey(),
                    'outstanding_balance_before_payment_cents' => $financials['outstanding_balance_cents'],
                    'balance_after_payment_cents' => $balanceAfterPayment,
                    'notes' => $payload['notes'] ?? null,
                ],
            ]);

            $recordTimelineEvent->handle(
                $visible,
                type: 'payment_received',
                title: 'Payment received',
                visibility: ServiceOrderTimelineEvent::VISIBILITY_CUSTOMER,
                actorMembership: $membership,
                payload: [
                    'amount_cents' => $payload['amount_cents'],
                    'payment_method' => $payload['payment_method'],
                    'balance_after_payment_cents' => $balanceAfterPayment,
                ],
            );
        });

        return response()->json([
            'data' => $this->serializeFinancials($visible->fresh(['charges', 'payments', 'customer'])),
        ], Response::HTTP_CREATED);
    }

    protected function resolveFinanceableServiceOrder(
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
    ): ServiceOrder {
        $membership = currentMembership();

        if ($membership === null) {
            abort(Response::HTTP_FORBIDDEN, 'Missing active membership context.');
        }

        if (! $membership->hasPermission('service_orders.finance')) {
            abort(Response::HTTP_FORBIDDEN, 'This membership cannot manage service order financials.');
        }

        $visible = $visibleServiceOrders->queryFor($membership)
            ->whereKey($serviceOrder->getKey())
            ->first();

        abort_if($visible === null, Response::HTTP_NOT_FOUND);

        return $visible->loadMissing(['customer', 'charges', 'payments']);
    }

    protected function storeCharge(
        Request $request,
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
        string $chargeType,
        string $timelineType,
        string $timelineTitle,
        bool $allowNegative = false,
    ): JsonResponse {
        $visible = $this->resolveFinanceableServiceOrder($serviceOrder, $visibleServiceOrders);
        $membership = currentMembership();
        $payload = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount_cents' => $allowNegative
                ? ['required', 'integer', 'not_in:0']
                : ['required', 'integer', 'min:1'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use (
            $visible,
            $membership,
            $payload,
            $chargeType,
            $timelineType,
            $timelineTitle,
            $recordTimelineEvent,
        ): void {
            ServiceOrderCharge::query()->create([
                'company_id' => $visible->company_id,
                'service_order_id' => $visible->getKey(),
                'created_by_membership_id' => $membership?->getKey(),
                'charge_type' => $chargeType,
                'description' => $payload['description'],
                'amount_cents' => $payload['amount_cents'],
                'currency' => strtoupper((string) ($payload['currency'] ?? 'BRL')),
                'metadata' => [
                    'notes' => $payload['notes'] ?? null,
                ],
            ]);

            $recordTimelineEvent->handle(
                $visible,
                type: $timelineType,
                title: $timelineTitle,
                visibility: ServiceOrderTimelineEvent::VISIBILITY_INTERNAL,
                actorMembership: $membership,
                payload: [
                    'charge_type' => $chargeType,
                    'amount_cents' => $payload['amount_cents'],
                    'currency' => strtoupper((string) ($payload['currency'] ?? 'BRL')),
                ],
            );
        });

        return response()->json([
            'data' => $this->serializeFinancials($visible->fresh(['charges', 'payments'])),
        ], Response::HTTP_CREATED);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeFinancials(ServiceOrder $serviceOrder): array
    {
        $summary = $this->summarizeFinancials($serviceOrder);

        return [
            'service_order_id' => $serviceOrder->getKey(),
            'summary' => $summary,
            'charges' => $serviceOrder->charges->map(fn (ServiceOrderCharge $charge): array => [
                'id' => $charge->getKey(),
                'charge_type' => $charge->charge_type,
                'description' => $charge->description,
                'amount_cents' => $charge->amount_cents,
                'currency' => $charge->currency,
                'source_quote_revision_id' => $charge->source_quote_revision_id,
                'created_by_membership_id' => $charge->created_by_membership_id,
                'metadata' => $charge->metadata,
                'created_at' => $charge->created_at?->toIso8601String(),
            ])->all(),
            'payments' => $serviceOrder->payments->map(fn (ServiceOrderPayment $payment): array => [
                'id' => $payment->getKey(),
                'amount_cents' => $payment->amount_cents,
                'currency' => $payment->currency,
                'payment_method' => $payment->payment_method,
                'notes' => $payment->notes,
                'received_at' => $payment->received_at?->toIso8601String(),
                'received_by_membership_id' => $payment->received_by_membership_id,
                'receipt_snapshot' => $payment->receipt_snapshot,
            ])->all(),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    protected function summarizeFinancials(ServiceOrder $serviceOrder): array
    {
        $totalChargedCents = (int) $serviceOrder->charges->sum('amount_cents');
        $totalPaidCents = (int) $serviceOrder->payments->sum('amount_cents');

        return [
            'currency' => (string) ($serviceOrder->charges->first()?->currency ?? $serviceOrder->payments->first()?->currency ?? 'BRL'),
            'total_charged_cents' => $totalChargedCents,
            'total_paid_cents' => $totalPaidCents,
            'outstanding_balance_cents' => $totalChargedCents - $totalPaidCents,
        ];
    }
}
