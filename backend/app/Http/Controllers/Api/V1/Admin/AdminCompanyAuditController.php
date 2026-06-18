<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Auditing\Models\BusinessAudit;
use App\Domains\Companies\Models\Company;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCompanyAuditController extends Controller
{
    public function index(Request $request, Company $company): JsonResponse
    {
        $filters = $request->validate([
            'category' => ['sometimes', 'string', 'max:80'],
            'event_type' => ['sometimes', 'string', 'max:120'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $actorUserIds = BusinessAudit::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->getKey())
            ->whereNull('actor_membership_id')
            ->pluck('context')
            ->map(fn ($context) => is_array($context) ? ($context['actor_user_id'] ?? null) : null)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $superAdminNames = User::query()
            ->whereIn('id', $actorUserIds)
            ->pluck('name', 'id');

        $audits = BusinessAudit::query()
            ->withoutGlobalScopes()
            ->with('actorMembership.user')
            ->where('company_id', $company->getKey())
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
            ->limit((int) ($filters['limit'] ?? 50))
            ->get();

        return response()->json([
            'data' => $audits->map(function (BusinessAudit $audit) use ($superAdminNames): array {
                $actorUserId = is_array($audit->context) ? ($audit->context['actor_user_id'] ?? null) : null;
                $isSuperAdminAction = (bool) (is_array($audit->context) ? ($audit->context['actor_super_admin'] ?? false) : false);

                return [
                    'id' => $audit->getKey(),
                    'company_id' => $audit->company_id,
                    'actor_membership_id' => $audit->actor_membership_id,
                    'actor_name' => $audit->actorMembership?->user?->name
                        ?? ($actorUserId ? $superAdminNames->get((int) $actorUserId) : null),
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
            })->all(),
        ]);
    }
}
