<?php

namespace App\Domains\Billing\Gateways;

use App\Domains\Billing\Models\BillingInvoice;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Companies\Models\Company;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class AsaasGateway
{
    protected function shouldUseLocalGateway(): bool
    {
        if (! filled(config('billing.asaas.api_key'))) {
            return true;
        }

        return app()->runningUnitTests() && ! config('billing.asaas.force_remote_in_tests', false);
    }

    protected function client(): PendingRequest
    {
        if ($this->shouldUseLocalGateway()) {
            abort(500, 'Billing gateway is not configured.');
        }

        return Http::baseUrl($this->baseUrl())
            ->withHeader('access_token', (string) config('billing.asaas.api_key'))
            ->acceptJson();
    }

    protected function baseUrl(): string
    {
        $configured = config('billing.asaas.base_url');
        if (filled($configured)) {
            return (string) $configured;
        }

        return config('billing.asaas.sandbox', true)
            ? 'https://api-sandbox.asaas.com/v3'
            : 'https://api.asaas.com/v3';
    }

    public function ensureCustomer(Company $company): array
    {
        if ($this->shouldUseLocalGateway()) {
            return ['id' => 'local-'.$company->getKey()];
        }

        if ($company->subscription?->gateway_customer_id) {
            return ['id' => $company->subscription->gateway_customer_id];
        }

        $response = $this->client()->post('/customers', [
            'name' => $company->trade_name ?: $company->name,
            'email' => $company->email,
            'cpfCnpj' => preg_replace('/\D+/', '', (string) $company->document) ?: null,
            'phone' => preg_replace('/\D+/', '', (string) $company->phone) ?: null,
        ])->throw()->json();

        return $response;
    }

    public function createSubscription(Company $company, Plan $plan, string $dueDate, string $callbackUrl): array
    {
        if ($this->shouldUseLocalGateway()) {
            return [
                'id' => 'local-sub-'.$company->getKey(),
                'customer' => 'local-'.$company->getKey(),
                'invoiceUrl' => null,
                'bankSlipUrl' => null,
            ];
        }

        $customer = $this->ensureCustomer($company);

        $payload = [
            'customer' => $customer['id'],
            'billingType' => 'BOLETO',
            'cycle' => 'MONTHLY',
            'nextDueDate' => $dueDate,
            'value' => $this->referenceValue($plan),
            'description' => sprintf('Plano %s - %s', $plan->name, $company->name),
        ];

        if (filled($callbackUrl)) {
            $payload['callback'] = ['successUrl' => $callbackUrl];
        }

        if (filled(config('billing.asaas.wallet_id'))) {
            $payload['walletId'] = (string) config('billing.asaas.wallet_id');
        }

        $subscription = $this->client()->post('/subscriptions', $payload)->throw()->json();

        // Busca o primeiro pagamento gerado para retornar as URLs de boleto/invoice
        $payments = $this->client()
            ->get('/subscriptions/' . $subscription['id'] . '/payments', ['limit' => 1])
            ->json('data', []);

        $firstPayment = $payments[0] ?? [];

        return array_merge($subscription, [
            'invoiceUrl' => $firstPayment['invoiceUrl'] ?? null,
            'bankSlipUrl' => $firstPayment['bankSlipUrl'] ?? null,
            'firstPaymentId' => $firstPayment['id'] ?? null,
        ]);
    }

    public function cancelSubscription(Subscription $subscription): void
    {
        if (! $subscription->gateway_subscription_id) {
            return;
        }

        $this->client()->delete('/subscriptions/'.$subscription->gateway_subscription_id)->throw();
    }

    public function fetchSubscription(string $gatewaySubscriptionId): array
    {
        if ($this->shouldUseLocalGateway()) {
            return ['id' => $gatewaySubscriptionId, 'status' => 'active'];
        }

        return $this->client()->get('/subscriptions/'.$gatewaySubscriptionId)->throw()->json();
    }

    public function fetchInvoice(string $gatewayInvoiceId): array
    {
        if ($this->shouldUseLocalGateway()) {
            return ['id' => $gatewayInvoiceId, 'status' => 'confirmed', 'value' => 0];
        }

        return $this->client()->get('/payments/'.$gatewayInvoiceId)->throw()->json();
    }

    public function storeInvoiceFromGateway(Company $company, Subscription $subscription, array $payload): BillingInvoice
    {
        return BillingInvoice::query()->updateOrCreate(
            ['gateway_invoice_id' => $payload['id'] ?? null],
            [
                'company_id' => $company->getKey(),
                'subscription_id' => $subscription->getKey(),
                'status' => $payload['status'] ?? 'pending',
                'amount_cents' => (int) round(((float) ($payload['value'] ?? 0)) * 100),
                'currency' => 'BRL',
                'due_at' => isset($payload['dueDate']) ? now()->parse($payload['dueDate']) : null,
                'paid_at' => isset($payload['paymentDate']) ? now()->parse($payload['paymentDate']) : null,
                'invoice_url' => $payload['invoiceUrl'] ?? null,
                'pdf_url' => $payload['bankSlipUrl'] ?? null,
                'payload' => $payload,
            ],
        );
    }

    protected function referenceValue(Plan $plan): float
    {
        return (float) (($plan->reference_price_cents ?? 0) / 100);
    }
}
