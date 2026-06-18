<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Actions\DispatchBillingNotification;
use App\Domains\Billing\Gateways\AsaasGateway;
use App\Domains\Billing\Models\Subscription;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BillingWebhookController extends Controller
{
    public function asaas(
        Request $request,
        AsaasGateway $gateway,
        DispatchBillingNotification $dispatchBillingNotification,
    ): JsonResponse
    {
        $token = (string) $request->header('asaas-access-token', $request->header('x-asaas-token', ''));
        abort_unless(hash_equals((string) config('billing.asaas.webhook_token'), $token), Response::HTTP_UNAUTHORIZED, 'Invalid webhook signature.');

        $payload = $request->all();
        $event = strtoupper((string) ($payload['event'] ?? $payload['type'] ?? ''));

        $gatewaySubscriptionId = data_get($payload, 'subscription.id') ?? data_get($payload, 'data.subscription.id') ?? data_get($payload, 'subscriptionId') ?? null;
        $gatewayCustomerId = data_get($payload, 'payment.customer.id') ?? data_get($payload, 'customer.id') ?? data_get($payload, 'data.customer.id') ?? null;
        $gatewayInvoiceId = data_get($payload, 'payment.id') ?? data_get($payload, 'data.payment.id') ?? data_get($payload, 'id') ?? null;

        $subscription = Subscription::query()
            ->with(['company', 'plan'])
            ->when($gatewaySubscriptionId, fn ($query) => $query->where('gateway_subscription_id', $gatewaySubscriptionId))
            ->when(! $gatewaySubscriptionId && $gatewayCustomerId, fn ($query) => $query->where('gateway_customer_id', $gatewayCustomerId))
            ->first();

        if ($subscription === null) {
            return response()->json(['message' => 'Webhook accepted but no local subscription matched.']);
        }

        $invoicePayload = $payload['payment'] ?? $payload['data']['payment'] ?? $payload;
        $invoice = null;

        if (in_array($event, ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED:TRUE'], true)) {
            $subscription->forceFill([
                'status' => Subscription::STATUS_ACTIVE,
                'expires_at' => ($subscription->expires_at->isFuture() ? $subscription->expires_at->copy() : now())->addDays(config('billing.asaas.default_cycle_days', 30)),
            ])->save();

            $invoice = $gateway->storeInvoiceFromGateway(
                $subscription->company,
                $subscription,
                $invoicePayload,
            );

            $dispatchBillingNotification->sendPaymentReceipt($subscription->fresh(['company', 'plan']), $invoice);
        }

        if (in_array($event, ['PAYMENT_OVERDUE', 'PAYMENT_REFUSED', 'SUBSCRIPTION_DELETED', 'SUBSCRIPTION_CANCELLED'], true)) {
            $subscription->forceFill([
                'status' => $event === 'SUBSCRIPTION_DELETED' || $event === 'SUBSCRIPTION_CANCELLED'
                    ? Subscription::STATUS_CANCELLED
                    : Subscription::STATUS_SUSPENDED,
            ])->save();
        }

        if ($gatewayInvoiceId) {
            $invoice ??= $gateway->storeInvoiceFromGateway($subscription->company, $subscription, $invoicePayload);
        }

        if (in_array($event, ['PAYMENT_OVERDUE', 'PAYMENT_REFUSED'], true)) {
            $dispatchBillingNotification->sendPaymentIssue($subscription->fresh(['company', 'plan']), $invoice, $event);
        }

        return response()->json(['message' => 'ok']);
    }
}
