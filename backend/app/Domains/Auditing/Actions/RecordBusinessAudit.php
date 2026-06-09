<?php

namespace App\Domains\Auditing\Actions;

use App\Domains\Auditing\Models\BusinessAudit;
use App\Domains\Auth\Models\Membership;
use Illuminate\Database\Eloquent\Model;

class RecordBusinessAudit
{
    /**
     * @param  array<string, mixed>|null  $context
     * @param  array<string, mixed>|null  $beforeValues
     * @param  array<string, mixed>|null  $afterValues
     */
    public function handle(
        int $companyId,
        string $category,
        string $eventType,
        ?Model $auditable = null,
        ?Membership $actorMembership = null,
        ?array $context = null,
        ?array $beforeValues = null,
        ?array $afterValues = null,
    ): BusinessAudit {
        return BusinessAudit::query()
            ->withoutGlobalScopes()
            ->create([
                'company_id' => $companyId,
                'actor_membership_id' => $actorMembership?->getKey(),
                'category' => $category,
                'event_type' => $eventType,
                'auditable_type' => $auditable?->getMorphClass(),
                'auditable_id' => $auditable?->getKey(),
                'context' => $context,
                'before_values' => $beforeValues,
                'after_values' => $afterValues,
                'occurred_at' => now(),
            ]);
    }
}
