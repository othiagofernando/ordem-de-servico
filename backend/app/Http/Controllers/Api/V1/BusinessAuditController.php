<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auditing\Models\BusinessAudit;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BusinessAuditController extends Controller
{
    /**
     * List tenant-scoped sensitive business audit records.
     *
     * @group Auditing
     */
    public function __invoke(Request $request): JsonResponse
    {
        $membership = currentMembership();
        $company = currentCompany();

        abort_if($membership === null, Response::HTTP_FORBIDDEN, 'Missing active membership context.');
        abort_if($company === null, Response::HTTP_FORBIDDEN, 'Missing active company context.');

        $payload = $request->validate([
            'category' => ['sometimes', 'string', 'max:80'],
            'event_type' => ['sometimes', 'string', 'max:120'],
        ]);

        $audits = BusinessAudit::query()
            ->with('actorMembership.user')
            ->when(
                filled($payload['category'] ?? null),
                fn ($query) => $query->where('category', $payload['category'])
            )
            ->when(
                filled($payload['event_type'] ?? null),
                fn ($query) => $query->where('event_type', $payload['event_type'])
            )
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $audits->map(fn (BusinessAudit $audit): array => [
                'id' => $audit->getKey(),
                'company_id' => $audit->company_id,
                'actor_membership_id' => $audit->actor_membership_id,
                'actor_name' => $audit->actorMembership?->user?->name,
                'category' => $audit->category,
                'event_type' => $audit->event_type,
                'auditable_type' => $audit->auditable_type,
                'auditable_id' => $audit->auditable_id,
                'context' => $audit->context,
                'before_values' => $audit->before_values,
                'after_values' => $audit->after_values,
                'occurred_at' => $audit->occurred_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
