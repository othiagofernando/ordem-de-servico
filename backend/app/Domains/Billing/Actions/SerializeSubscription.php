<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Companies\Models\Company;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderAttachment;

class SerializeSubscription
{
    public function handle(?Subscription $subscription): ?array
    {
        if ($subscription === null) {
            return null;
        }

        $company = $subscription->company ?? Company::withTrashed()->find($subscription->company_id);

        $serviceOrdersThisMonth = ServiceOrder::query()
            ->withoutGlobalScopes()
            ->where('company_id', $subscription->company_id)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        $attachmentsBytes = ServiceOrderAttachment::query()
            ->withoutGlobalScopes()
            ->where('company_id', $subscription->company_id)
            ->sum('file_size_bytes');

        $activeUsers = $company?->memberships()
            ->withoutGlobalScopes()
            ->where('status', 'active')
            ->count() ?? 0;

        $maxAttachmentBytes = $subscription->plan?->max_attachments_storage_mb !== null
            ? $subscription->plan->max_attachments_storage_mb * 1024 * 1024
            : null;

        return [
            'id' => $subscription->getKey(),
            'status' => $subscription->status,
            'is_trial' => $subscription->gateway_subscription_id === null,
            'started_at' => $subscription->started_at?->toIso8601String(),
            'expires_at' => $subscription->expires_at?->toIso8601String(),
            'days_until_expiration' => $subscription->daysUntilExpiration(),
            'is_in_grace_period' => $subscription->status === Subscription::STATUS_ACTIVE
                && $subscription->expires_at->isPast()
                && $subscription->expires_at->copy()->addDays(Subscription::GRACE_DAYS)->isFuture(),
            'plan' => [
                'id' => $subscription->plan?->getKey(),
                'slug' => $subscription->plan?->slug,
                'name' => $subscription->plan?->name,
                'limits' => [
                    'max_users' => $subscription->plan?->max_users,
                    'max_service_orders_per_month' => $subscription->plan?->max_service_orders_per_month,
                    'max_attachments_storage_mb' => $subscription->plan?->max_attachments_storage_mb,
                ],
                'reference_price_cents' => $subscription->plan?->reference_price_cents,
            ],
            'usage' => [
                'active_users' => $activeUsers,
                'service_orders_this_month' => $serviceOrdersThisMonth,
                'attachments_storage_bytes' => $attachmentsBytes,
                'attachments_storage_mb' => round($attachmentsBytes / 1024 / 1024, 2),
                'attachments_storage_limit_mb' => $subscription->plan?->max_attachments_storage_mb,
                'attachments_storage_remaining_mb' => $maxAttachmentBytes === null
                    ? null
                    : round(max($maxAttachmentBytes - $attachmentsBytes, 0) / 1024 / 1024, 2),
            ],
        ];
    }
}
