<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Actions\ResolveCurrentSubscription;
use App\Domains\Billing\Actions\SerializeSubscription;
use App\Domains\Billing\Models\Plan;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController extends Controller
{
    public function __invoke(
        ResolveCurrentSubscription $resolveCurrentSubscription,
        SerializeSubscription $serializeSubscription,
    ): JsonResponse {
        $company = currentCompany();

        abort_if($company === null, Response::HTTP_FORBIDDEN, 'Missing active company context.');

        return response()->json([
            'data' => $serializeSubscription->handle($subscription = $resolveCurrentSubscription->handle($company)),
            'plans' => Plan::query()->orderBy('id')->get()->map(fn (Plan $plan): array => [
                'id' => $plan->getKey(),
                'slug' => $plan->slug,
                'name' => $plan->name,
                'limits' => [
                    'max_users' => $plan->max_users,
                    'max_service_orders_per_month' => $plan->max_service_orders_per_month,
                    'max_attachments_storage_mb' => $plan->max_attachments_storage_mb,
                ],
                'reference_price_cents' => $plan->reference_price_cents,
            ])->all(),
            'invoices' => $subscription
                ? $subscription->billingInvoices()->latest('id')->get()->map(fn ($invoice): array => [
                    'id' => $invoice->getKey(),
                    'status' => $invoice->status,
                    'amount_cents' => $invoice->amount_cents,
                    'currency' => $invoice->currency,
                    'due_at' => $invoice->due_at?->toIso8601String(),
                    'paid_at' => $invoice->paid_at?->toIso8601String(),
                    'invoice_url' => $invoice->invoice_url,
                    'pdf_url' => $invoice->pdf_url,
                ])->all()
                : [],
        ]);
    }
}
