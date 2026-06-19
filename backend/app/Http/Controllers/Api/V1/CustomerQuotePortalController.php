<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auditing\Actions\RecordBusinessAudit;
use App\Domains\Companies\Actions\ResolveCompanyOperationalSettings;
use App\Domains\Notifications\Actions\CreateUserNotification;
use App\Domains\Companies\Models\CompanyOperationalSetting;
use App\Domains\ServiceOrders\Actions\BuildOperationalDocumentPayload;
use App\Domains\ServiceOrders\Actions\NormalizeQuoteRevisionStates;
use App\Domains\ServiceOrders\Actions\RecordServiceOrderTimelineEvent;
use App\Domains\ServiceOrders\Models\QuoteRevision;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderPayment;
use App\Domains\ServiceOrders\Models\ServiceOrderTimelineEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerQuotePortalController extends Controller
{
    /**
     * View one service order and its current quote through a portal token.
     *
     * @group Customer Portal
     */
    public function show(
        string $token,
        NormalizeQuoteRevisionStates $normalizeQuoteRevisionStates,
        ResolveCompanyOperationalSettings $resolveCompanyOperationalSettings,
    ): JsonResponse {
        $quote = $this->resolveQuoteByPortalToken($token);
        $normalizeQuoteRevisionStates->handle($quote->serviceOrder);
        $quote->refresh();
        $settings = $resolveCompanyOperationalSettings->handle($quote->serviceOrder->company);

        return response()->json([
            'data' => $this->serializePortalQuote($quote, $settings),
        ]);
    }

    /**
     * View the printable current quote document through the same portal token.
     *
     * @group Customer Portal
     */
    public function documentQuote(
        string $token,
        NormalizeQuoteRevisionStates $normalizeQuoteRevisionStates,
        BuildOperationalDocumentPayload $buildOperationalDocumentPayload,
    ): JsonResponse {
        $quote = $this->resolveQuoteByPortalToken($token);
        $normalizeQuoteRevisionStates->handle($quote->serviceOrder);
        $quote->refresh();

        return response()->json([
            'data' => $buildOperationalDocumentPayload->quote($quote->serviceOrder, $quote),
        ]);
    }

    /**
     * View one payment receipt document through the portal token of the same service order.
     *
     * @group Customer Portal
     */
    public function documentReceipt(
        string $token,
        ServiceOrderPayment $payment,
        BuildOperationalDocumentPayload $buildOperationalDocumentPayload,
    ): JsonResponse {
        $quote = $this->resolveQuoteByPortalToken($token);
        $serviceOrder = $quote->serviceOrder->loadMissing(['company', 'customer', 'customerContact']);

        abort_if(
            $payment->service_order_id !== $serviceOrder->getKey() || $payment->company_id !== $serviceOrder->company_id,
            Response::HTTP_NOT_FOUND,
        );

        return response()->json([
            'data' => $buildOperationalDocumentPayload->receipt($serviceOrder, $payment),
        ]);
    }

    /**
     * View the printable delivery term document through the portal token.
     *
     * @group Customer Portal
     */
    public function documentDelivery(
        string $token,
        BuildOperationalDocumentPayload $buildOperationalDocumentPayload,
    ): JsonResponse {
        $quote = $this->resolveQuoteByPortalToken($token);
        $serviceOrder = $quote->serviceOrder->loadMissing(['company', 'customer', 'customerContact', 'equipment', 'charges', 'payments']);

        if ($serviceOrder->status !== ServiceOrder::STATUS_DELIVERED || $serviceOrder->delivered_at === null) {
            return response()->json([
                'message' => 'This service order does not yet expose a delivery term.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'data' => $buildOperationalDocumentPayload->delivery($serviceOrder),
        ]);
    }

    /**
     * Return all other service orders for the same customer in the same company.
     *
     * @group Customer Portal
     */
    public function history(string $token): JsonResponse
    {
        $quote = $this->resolveQuoteByPortalToken($token);
        $quote->serviceOrder->loadMissing(['customer']);

        $customerId = $quote->serviceOrder->customer_id;
        $companyId = $quote->serviceOrder->company_id;
        $currentId = $quote->serviceOrder->getKey();

        if ($customerId === null) {
            return response()->json(['data' => []]);
        }

        $orders = ServiceOrder::query()
            ->where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('id', '!=', $currentId)
            ->orderByDesc('opened_at')
            ->with(['payments'])
            ->get();

        $data = $orders->map(fn (ServiceOrder $order): array => [
            'id' => $order->getKey(),
            'reference' => $order->reference,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'opened_at' => $order->opened_at?->toIso8601String(),
            'title' => $order->title,
        ])->values()->all();

        return response()->json(['data' => $data]);
    }

    /**
     * Approve or reject a quote through a portal token.
     *
     * @group Customer Portal
     */
    public function decide(
        Request $request,
        string $token,
        NormalizeQuoteRevisionStates $normalizeQuoteRevisionStates,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
        RecordBusinessAudit $recordBusinessAudit,
        ResolveCompanyOperationalSettings $resolveCompanyOperationalSettings,
    ): JsonResponse {
        $quote = $this->resolveQuoteByPortalToken($token);
        $normalizeQuoteRevisionStates->handle($quote->serviceOrder);
        $quote->refresh();
        $settings = $resolveCompanyOperationalSettings->handle($quote->serviceOrder->company);

        $payload = $request->validate([
            'decision' => ['required', 'string', 'in:approve,reject'],
        ]);

        if ($quote->status === QuoteRevision::STATUS_EXPIRED) {
            return response()->json([
                'message' => 'This quote is no longer pending for customer decision.',
            ], Response::HTTP_CONFLICT);
        }

        $requestedStatus = $payload['decision'] === 'approve'
            ? QuoteRevision::STATUS_APPROVED
            : QuoteRevision::STATUS_REJECTED;

        if (in_array($quote->status, [QuoteRevision::STATUS_APPROVED, QuoteRevision::STATUS_REJECTED], true)) {
            if ($quote->status === $requestedStatus) {
                return response()->json([
                    'data' => $this->serializePortalQuote($quote, $settings),
                ]);
            }

            return response()->json([
                'message' => 'This quote already has a final customer decision.',
            ], Response::HTTP_CONFLICT);
        }

        if ($quote->status !== QuoteRevision::STATUS_PENDING) {
            return response()->json([
                'message' => 'This quote is no longer pending for customer decision.',
            ], Response::HTTP_CONFLICT);
        }

        $quote->forceFill([
            'status' => $requestedStatus,
            'decisioned_at' => now(),
            'decision_token_hash' => hash('sha256', $token),
            'decision_ip' => $request->ip(),
            'decision_user_agent' => (string) $request->userAgent(),
            'decision_idempotency_key' => (string) $request->header('Idempotency-Key', ''),
        ])->save();

        $recordTimelineEvent->handle(
            $quote->serviceOrder,
            type: $requestedStatus === QuoteRevision::STATUS_APPROVED ? 'quote_approved' : 'quote_rejected',
            title: $requestedStatus === QuoteRevision::STATUS_APPROVED ? 'Quote approved' : 'Quote rejected',
            visibility: ServiceOrderTimelineEvent::VISIBILITY_CUSTOMER,
            actorMembership: null,
            payload: [
                'quote_revision_id' => $quote->getKey(),
                'revision_number' => $quote->revision_number,
                'decisioned_at' => $quote->decisioned_at?->toIso8601String(),
                'decision_ip' => $quote->decision_ip,
            ],
        );

        $recordBusinessAudit->handle(
            $quote->serviceOrder->company_id,
            'quote',
            'quote_customer_decision_recorded',
            auditable: $quote,
            actorMembership: null,
            context: [
                'service_order_id' => $quote->service_order_id,
                'quote_revision_id' => $quote->getKey(),
                'revision_number' => $quote->revision_number,
            ],
            afterValues: [
                'status' => $quote->status,
                'decisioned_at' => $quote->decisioned_at?->toIso8601String(),
                'decision_ip' => $quote->decision_ip,
                'decision_user_agent' => $quote->decision_user_agent,
                'decision_idempotency_key' => $quote->decision_idempotency_key,
            ],
        );

        // Notify relevant memberships about customer's decision
        try {
            $serviceOrder = $quote->serviceOrder->loadMissing('company');
            $companySlug = $serviceOrder->company->slug ?? '';
            $actionUrl = "/{$companySlug}/ordens-de-servico/{$serviceOrder->id}";
            $isApproved = $requestedStatus === QuoteRevision::STATUS_APPROVED;
            $notifType = $isApproved ? 'quote_approved' : 'quote_rejected';
            $notifTitle = $isApproved ? 'Orçamento aprovado' : 'Orçamento recusado';
            $notifBody = $isApproved
                ? "Cliente aprovou o orçamento da O.S. {$serviceOrder->reference}."
                : "Cliente recusou o orçamento da O.S. {$serviceOrder->reference}.";

            $createUserNotification = app(CreateUserNotification::class);

            // Notify assigned technician (if any)
            if ($serviceOrder->assigned_membership_id !== null) {
                $createUserNotification->handle(
                    companyId: $serviceOrder->company_id,
                    membershipId: $serviceOrder->assigned_membership_id,
                    type: $notifType,
                    title: $notifTitle,
                    body: $notifBody,
                    actionUrl: $actionUrl,
                );
            }

            // Notify all admins/managers in the company
            $adminMemberships = \App\Domains\Auth\Models\Membership::query()
                ->withoutGlobalScopes()
                ->where('company_id', $serviceOrder->company_id)
                ->whereIn('role', ['administrator', 'manager'])
                ->where('status', 'active')
                ->get();

            foreach ($adminMemberships as $adminMembership) {
                // Skip if already notified as assigned tech
                if ($adminMembership->getKey() === $serviceOrder->assigned_membership_id) {
                    continue;
                }
                $createUserNotification->handle(
                    companyId: $serviceOrder->company_id,
                    membershipId: $adminMembership->getKey(),
                    type: $notifType,
                    title: $notifTitle,
                    body: $notifBody,
                    actionUrl: $actionUrl,
                );
            }
        } catch (\Throwable) {
            // Notification failure must not break the main flow
        }

        return response()->json([
            'data' => $this->serializePortalQuote($quote->fresh(), $settings),
        ]);
    }

    protected function resolveQuoteByPortalToken(string $token): QuoteRevision
    {
        $quote = QuoteRevision::query()
            ->with([
                'serviceOrder.company',
                'serviceOrder.customer',
                'serviceOrder.customerContact',
                'serviceOrder.equipment',
                'serviceOrder.payments',
                'serviceOrder.charges',
                'serviceOrder.timelineEvents',
            ])
            ->where('portal_token_hash', hash('sha256', $token))
            ->first();

        if ($quote === null || $quote->portal_token_expires_at === null || $quote->portal_token_expires_at->isPast()) {
            abort(Response::HTTP_NOT_FOUND, 'Invalid or expired portal token.');
        }

        return $quote;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializePortalQuote(QuoteRevision $quote, CompanyOperationalSetting $settings): array
    {
        $serviceOrder = $quote->serviceOrder;
        $customerTimeline = $serviceOrder->timelineEvents
            ->where('visibility', ServiceOrderTimelineEvent::VISIBILITY_CUSTOMER)
            ->values();
        $paymentDocuments = $serviceOrder->payments
            ->map(fn (ServiceOrderPayment $payment): array => [
                'payment_id' => $payment->getKey(),
                'amount_cents' => $payment->amount_cents,
                'currency' => $payment->currency,
                'payment_method' => $payment->payment_method,
                'received_at' => $payment->received_at?->toIso8601String(),
            ])
            ->values();

        return [
            'company' => [
                'brand_name' => $settings->brandName($quote->serviceOrder->company),
                'customer_contact_line' => $settings->customer_contact_line,
            ],
            'portal' => [
                'intro_text' => $settings->portal_intro_text,
                'footer_text' => $settings->portal_footer_text,
                'approved_text' => $settings->portalApprovedText(),
                'rejected_text' => $settings->portalRejectedText(),
            ],
            'service_order' => [
                'id' => $serviceOrder->getKey(),
                'reference' => $serviceOrder->reference,
                'order_number' => $serviceOrder->order_number,
                'title' => $serviceOrder->title,
                'status' => $serviceOrder->status,
                'opened_at' => $serviceOrder->opened_at?->toIso8601String(),
                'reported_problem' => $serviceOrder->reported_problem,
                'diagnosis_customer_summary' => $serviceOrder->diagnosis_customer_summary,
                'customer' => $serviceOrder->customer === null ? null : [
                    'id' => $serviceOrder->customer->getKey(),
                    'name' => $serviceOrder->customer->name,
                ],
                'equipment' => [
                    'description' => $serviceOrder->equipment_description,
                    'category' => $serviceOrder->equipment?->category,
                    'brand' => $serviceOrder->equipment?->brand,
                    'model' => $serviceOrder->equipment?->model,
                ],
                'timeline' => $customerTimeline->map(fn (ServiceOrderTimelineEvent $event): array => [
                    'id' => $event->getKey(),
                    'type' => $event->type,
                    'title' => $event->title,
                    'occurred_at' => $event->occurred_at?->toIso8601String(),
                ])->all(),
                'documents' => [
                    'quote' => [
                        'available' => true,
                    ],
                    'receipts' => $paymentDocuments->all(),
                    'delivery_term' => [
                        'available' => $serviceOrder->status === ServiceOrder::STATUS_DELIVERED && $serviceOrder->delivered_at !== null,
                    ],
                ],
            ],
            'quote' => [
                'id' => $quote->getKey(),
                'revision_number' => $quote->revision_number,
                'status' => $quote->status,
                'line_items' => $quote->line_items,
                'currency' => $quote->currency,
                'total_cents' => $quote->total_cents,
                'notes' => $quote->notes,
                'valid_until' => $quote->valid_until?->toIso8601String(),
                'submitted_at' => $quote->submitted_at?->toIso8601String(),
                'decisioned_at' => $quote->decisioned_at?->toIso8601String(),
            ],
        ];
    }
}
