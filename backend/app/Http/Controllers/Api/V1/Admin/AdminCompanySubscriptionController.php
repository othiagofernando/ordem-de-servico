<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Auditing\Actions\RecordBusinessAudit;
use App\Domains\Billing\Actions\ResolveCurrentSubscription;
use App\Domains\Billing\Actions\SerializeSubscription;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Companies\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCompanySubscriptionController extends Controller
{
    public function update(
        Request $request,
        Company $company,
        ResolveCurrentSubscription $resolveCurrentSubscription,
        SerializeSubscription $serializeSubscription,
        RecordBusinessAudit $recordBusinessAudit,
    ): JsonResponse {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'expires_at' => ['required', 'date'],
            'started_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:active,suspended,cancelled'],
        ]);

        $existing = $resolveCurrentSubscription->handle($company);

        $subscription = Subscription::query()->updateOrCreate(
            ['company_id' => $company->getKey()],
            [
                'plan_id' => $data['plan_id'],
                'started_at' => $data['started_at'] ?? ($existing?->started_at ?? now()),
                'expires_at' => $data['expires_at'],
                'status' => $data['status'] ?? Subscription::STATUS_ACTIVE,
            ],
        )->fresh(['plan', 'company']);

        $recordBusinessAudit->handle(
            companyId: $company->getKey(),
            category: 'billing',
            eventType: 'subscription.updated',
            auditable: $company,
            actorMembership: null,
            context: [
                'actor_user_id' => $request->user()?->getKey(),
                'actor_super_admin' => true,
            ],
            beforeValues: $existing ? $serializeSubscription->handle($existing) : null,
            afterValues: $serializeSubscription->handle($subscription),
        );

        return response()->json([
            'data' => $serializeSubscription->handle($subscription),
        ]);
    }

    public function extend(
        Request $request,
        Company $company,
        ResolveCurrentSubscription $resolveCurrentSubscription,
        SerializeSubscription $serializeSubscription,
        RecordBusinessAudit $recordBusinessAudit,
    ): JsonResponse {
        $data = $request->validate([
            'days' => ['nullable', 'integer', 'min:1'],
            'months' => ['nullable', 'integer', 'min:1'],
        ]);

        $subscription = $resolveCurrentSubscription->handle($company);
        abort_if($subscription === null, 422, 'A empresa precisa ter uma assinatura antes de renovar.');

        $before = $serializeSubscription->handle($subscription);
        $expiresAt = $subscription->expires_at->isFuture() ? $subscription->expires_at->copy() : now();

        if (filled($data['days'] ?? null)) {
            $expiresAt->addDays((int) $data['days']);
        }

        if (filled($data['months'] ?? null)) {
            $expiresAt->addMonthsNoOverflow((int) $data['months']);
        }

        $subscription->forceFill([
            'expires_at' => $expiresAt,
            'status' => Subscription::STATUS_ACTIVE,
        ])->save();

        $fresh = $subscription->fresh(['plan', 'company']);

        $recordBusinessAudit->handle(
            companyId: $company->getKey(),
            category: 'billing',
            eventType: 'subscription.extended',
            auditable: $company,
            actorMembership: null,
            context: [
                'actor_user_id' => $request->user()?->getKey(),
                'actor_super_admin' => true,
                'days' => $data['days'] ?? null,
                'months' => $data['months'] ?? null,
            ],
            beforeValues: $before,
            afterValues: $serializeSubscription->handle($fresh),
        );

        return response()->json([
            'data' => $serializeSubscription->handle($fresh),
        ]);
    }
}
