<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Billing\Models\BillingInvoice;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBillingInvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'string', 'max:120'],
            'status' => ['sometimes', 'string', 'max:60'],
            'plan_id' => ['sometimes', 'integer', 'exists:plans,id'],
            'overdue' => ['sometimes', 'boolean'],
        ]);

        $invoices = BillingInvoice::query()
            ->with(['company', 'subscription.plan'])
            ->when(
                filled($filters['q'] ?? null),
                fn ($query) => $query->whereHas('company', function ($companyQuery) use ($filters): void {
                    $term = trim((string) $filters['q']);

                    $companyQuery->where('name', 'like', '%'.$term.'%')
                        ->orWhere('document', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%');
                })
            )
            ->when(
                filled($filters['status'] ?? null),
                fn ($query) => $query->where('status', $filters['status'])
            )
            ->when(
                isset($filters['plan_id']),
                fn ($query) => $query->whereHas('subscription', fn ($subscriptionQuery) => $subscriptionQuery->where('plan_id', $filters['plan_id']))
            )
            ->when(
                filter_var($filters['overdue'] ?? false, FILTER_VALIDATE_BOOLEAN),
                fn ($query) => $query
                    ->whereNull('paid_at')
                    ->whereDate('due_at', '<', now()->toDateString())
            )
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $invoices->map(fn (BillingInvoice $invoice): array => $this->serializeInvoice($invoice))->all(),
            'summary' => [
                'total' => BillingInvoice::query()->count(),
                'pending' => BillingInvoice::query()->where('status', 'pending')->count(),
                'overdue' => BillingInvoice::query()
                    ->whereNull('paid_at')
                    ->whereDate('due_at', '<', now()->toDateString())
                    ->count(),
                'paid' => BillingInvoice::query()->whereNotNull('paid_at')->count(),
            ],
        ]);
    }

    public function show(BillingInvoice $billingInvoice): JsonResponse
    {
        $billingInvoice->load(['company', 'subscription.plan']);

        return response()->json([
            'data' => $this->serializeInvoice($billingInvoice, true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeInvoice(BillingInvoice $invoice, bool $withPayload = false): array
    {
        $data = [
            'id' => $invoice->getKey(),
            'gateway_invoice_id' => $invoice->gateway_invoice_id,
            'status' => $invoice->status,
            'amount_cents' => $invoice->amount_cents,
            'currency' => $invoice->currency,
            'due_at' => $invoice->due_at?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'invoice_url' => $invoice->invoice_url,
            'pdf_url' => $invoice->pdf_url,
            'created_at' => $invoice->created_at?->toIso8601String(),
            'updated_at' => $invoice->updated_at?->toIso8601String(),
            'company' => [
                'id' => $invoice->company?->getKey(),
                'name' => $invoice->company?->name,
                'document' => $invoice->company?->document,
                'email' => $invoice->company?->email,
                'status' => $invoice->company?->status,
            ],
            'subscription' => [
                'id' => $invoice->subscription?->getKey(),
                'status' => $invoice->subscription?->status,
                'plan' => [
                    'id' => $invoice->subscription?->plan?->getKey(),
                    'name' => $invoice->subscription?->plan?->name,
                    'slug' => $invoice->subscription?->plan?->slug,
                ],
            ],
        ];

        if ($withPayload) {
            $data['payload'] = $invoice->payload;
        }

        return $data;
    }
}
