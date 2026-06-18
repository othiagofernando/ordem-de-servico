<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Auth\Models\Membership;
use App\Domains\Billing\Mail\BillingLifecycleMail;
use App\Domains\Billing\Models\BillingInvoice;
use App\Domains\Billing\Models\BillingNotificationLog;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Companies\Models\Company;
use App\Domains\Companies\Support\FrontendTenantUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class DispatchBillingNotification
{
    public function __construct(
        protected FrontendTenantUrl $frontendTenantUrl,
    ) {}

    public function sendRenewalReminder(Subscription $subscription, int $daysUntilExpiration): void
    {
        $this->send(
            subscription: $subscription,
            kind: $daysUntilExpiration <= 3 ? 'renewal-reminder-urgent' : 'renewal-reminder',
            eventKey: sprintf('renewal-reminder:%s:%s:%d', $subscription->getKey(), $subscription->expires_at->toDateString(), $daysUntilExpiration),
            subject: $daysUntilExpiration <= 3
                ? 'Renovacao urgente da assinatura'
                : 'Lembrete de renovacao da assinatura',
            title: $daysUntilExpiration <= 3
                ? 'Renovacao urgente'
                : 'Lembrete de renovacao',
            lines: [
                sprintf('O plano %s da empresa %s vence em %d dia(s).', $subscription->plan?->name ?? 'atual', $subscription->company?->name ?? 'informada', $daysUntilExpiration),
                'Para evitar interrupcao de acesso, gere a cobranca e conclua o pagamento antes do vencimento.',
            ],
            actionText: 'Abrir plano da empresa',
            actionUrl: $this->companyPlanUrl($subscription->company),
        );
    }

    public function sendSuspensionGraceNotice(Subscription $subscription): void
    {
        $this->send(
            subscription: $subscription,
            kind: 'suspension-grace-notice',
            eventKey: sprintf('suspension-grace:%s:%s', $subscription->getKey(), $subscription->expires_at->toDateString()),
            subject: 'Assinatura vencida: periodo de tolerancia iniciado',
            title: 'Assinatura vencida',
            lines: [
                sprintf('A assinatura da empresa %s venceu hoje.', $subscription->company?->name ?? 'informada'),
                sprintf('O acesso permanece liberado por %d dia(s) de tolerancia. Depois disso, a empresa sera suspensa ate a regularizacao do pagamento.', Subscription::GRACE_DAYS),
            ],
            actionText: 'Regularizar assinatura',
            actionUrl: $this->companyPlanUrl($subscription->company),
        );
    }

    public function sendPaymentIssue(Subscription $subscription, ?BillingInvoice $invoice, string $event): void
    {
        $statusLabel = $event === 'PAYMENT_OVERDUE' ? 'pagamento em atraso' : 'pagamento recusado';

        $this->send(
            subscription: $subscription,
            kind: 'payment-issue',
            eventKey: sprintf('payment-issue:%s:%s:%s', $subscription->getKey(), $invoice?->gateway_invoice_id ?? 'no-invoice', strtolower($event)),
            subject: 'Problema na cobranca da assinatura',
            title: 'Problema no pagamento',
            lines: array_values(array_filter([
                sprintf('Identificamos %s para a empresa %s.', $statusLabel, $subscription->company?->name ?? 'informada'),
                $invoice?->due_at ? sprintf('Vencimento da cobranca: %s.', $invoice->due_at->format('d/m/Y')) : null,
                'Revise a cobranca e regularize o pagamento para evitar bloqueio de acesso.',
            ])),
            actionText: $invoice?->invoice_url ? 'Abrir cobranca' : 'Abrir plano da empresa',
            actionUrl: $invoice?->invoice_url ?: $this->companyPlanUrl($subscription->company),
        );
    }

    public function sendPaymentReceipt(Subscription $subscription, ?BillingInvoice $invoice): void
    {
        $this->send(
            subscription: $subscription,
            kind: 'payment-receipt',
            eventKey: sprintf('payment-receipt:%s:%s', $subscription->getKey(), $invoice?->gateway_invoice_id ?? 'no-invoice'),
            subject: 'Pagamento confirmado e assinatura renovada',
            title: 'Pagamento confirmado',
            lines: array_values(array_filter([
                sprintf('Recebemos o pagamento da assinatura da empresa %s.', $subscription->company?->name ?? 'informada'),
                $subscription->expires_at ? sprintf('Nova data de renovacao: %s.', $subscription->expires_at->format('d/m/Y')) : null,
                $invoice?->amount_cents ? sprintf('Valor confirmado: R$ %s.', number_format($invoice->amount_cents / 100, 2, ',', '.')) : null,
            ])),
            actionText: $invoice?->pdf_url ? 'Baixar comprovante' : 'Abrir plano da empresa',
            actionUrl: $invoice?->pdf_url ?: $this->companyPlanUrl($subscription->company),
        );
    }

    /**
     * @param  array<int, string>  $lines
     */
    protected function send(
        Subscription $subscription,
        string $kind,
        string $eventKey,
        string $subject,
        string $title,
        array $lines,
        ?string $actionText = null,
        ?string $actionUrl = null,
    ): void {
        if (BillingNotificationLog::query()->where('event_key', $eventKey)->exists()) {
            return;
        }

        $subscription->loadMissing(['company', 'plan']);
        $recipients = $this->resolveRecipients($subscription->company);

        if ($recipients->isEmpty()) {
            return;
        }

        foreach ($recipients as $recipient) {
            Mail::to($recipient['email'], $recipient['name'])->queue(new BillingLifecycleMail(
                subjectLine: $subject,
                title: $title,
                companyName: $subscription->company?->name ?? 'Empresa',
                lines: $lines,
                actionUrl: $actionUrl,
                actionText: $actionText,
                footer: 'Este e-mail foi enviado automaticamente pelo sistema de faturamento.',
            ));
        }

        BillingNotificationLog::query()->create([
            'company_id' => $subscription->company_id,
            'subscription_id' => $subscription->getKey(),
            'kind' => $kind,
            'event_key' => $eventKey,
            'payload' => [
                'subject' => $subject,
                'recipient_count' => $recipients->count(),
            ],
            'sent_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, array{email: string, name: string|null}>
     */
    protected function resolveRecipients(?Company $company): Collection
    {
        if ($company === null) {
            return collect();
        }

        $company->loadMissing('memberships.user');

        $recipients = collect();

        if (filled($company->email)) {
            $recipients->push([
                'email' => (string) $company->email,
                'name' => $company->trade_name ?: $company->name,
            ]);
        }

        foreach ($company->memberships as $membership) {
            if ($membership->status !== Membership::STATUS_ACTIVE || $membership->role !== 'administrator') {
                continue;
            }

            if (! filled($membership->user?->email)) {
                continue;
            }

            $recipients->push([
                'email' => (string) $membership->user->email,
                'name' => $membership->user->name,
            ]);
        }

        return $recipients
            ->unique(fn (array $recipient): string => mb_strtolower($recipient['email']))
            ->values();
    }

    protected function companyPlanUrl(?Company $company): string
    {
        return $this->frontendTenantUrl->billingPlan($company);
    }
}
