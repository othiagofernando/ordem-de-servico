<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Auditing\Models\BusinessAudit;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'company_id' => ['sometimes', 'integer', 'exists:companies,id'],
            'category' => ['sometimes', 'string', 'max:80'],
            'event_type' => ['sometimes', 'string', 'max:120'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $audits = BusinessAudit::query()
            ->withoutGlobalScopes()
            ->with(['actorMembership.user', 'company'])
            ->when(
                filled($filters['company_id'] ?? null),
                fn ($query) => $query->where('company_id', $filters['company_id'])
            )
            ->when(
                filled($filters['category'] ?? null),
                fn ($query) => $query->where('category', $filters['category'])
            )
            ->when(
                filled($filters['event_type'] ?? null),
                fn ($query) => $query->where('event_type', $filters['event_type'])
            )
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit((int) ($filters['limit'] ?? 100))
            ->get();

        $actorUserIds = $audits
            ->pluck('context')
            ->map(fn ($context) => is_array($context) ? ($context['actor_user_id'] ?? null) : null)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $superAdminNames = User::query()
            ->whereIn('id', $actorUserIds)
            ->pluck('name', 'id');

        return response()->json([
            'data' => $audits->map(function (BusinessAudit $audit) use ($superAdminNames): array {
                return $this->serializeAudit($audit, $superAdminNames->all());
            })->all(),
        ]);
    }

    public function show(BusinessAudit $audit): JsonResponse
    {
        $audit->withoutRelations();
        $audit->load(['actorMembership.user', 'company']);

        $context = is_array($audit->context) ? $audit->context : [];
        $actorUserId = $context['actor_user_id'] ?? null;

        $superAdminNames = User::query()
            ->when(is_numeric($actorUserId), fn ($query) => $query->where('id', (int) $actorUserId))
            ->pluck('name', 'id')
            ->all();

        return response()->json([
            'data' => $this->serializeAudit($audit, $superAdminNames),
        ]);
    }

    /**
     * @param  array<int, string>  $superAdminNames
     * @return array<string, mixed>
     */
    protected function serializeAudit(BusinessAudit $audit, array $superAdminNames): array
    {
        $context = is_array($audit->context) ? $audit->context : [];
        $actorUserId = $context['actor_user_id'] ?? null;
        $isSuperAdminAction = (bool) ($context['actor_super_admin'] ?? false);

        return [
            'id' => $audit->getKey(),
            'company_id' => $audit->company_id,
            'company_name' => $audit->company?->name,
            'actor_membership_id' => $audit->actor_membership_id,
            'actor_name' => $audit->actorMembership?->user?->name
                ?? ($actorUserId ? ($superAdminNames[(int) $actorUserId] ?? null) : null),
            'actor_type' => $isSuperAdminAction ? 'super_admin' : 'tenant_membership',
            'category' => $audit->category,
            'event_type' => $audit->event_type,
            'auditable_type' => $audit->auditable_type,
            'auditable_id' => $audit->auditable_id,
            'context' => $audit->context,
            'before_values' => $audit->before_values,
            'after_values' => $audit->after_values,
            'occurred_at' => $audit->occurred_at?->toIso8601String(),
        ];
    }
}
