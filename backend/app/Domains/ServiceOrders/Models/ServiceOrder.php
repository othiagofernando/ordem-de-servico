<?php

namespace App\Domains\ServiceOrders\Models;

use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Concerns\BelongsToCompany;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerContact;
use App\Domains\Equipments\Models\Equipment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'customer_id',
    'customer_contact_id',
    'equipment_id',
    'intake_checklist_template_id',
    'reference',
    'draft_reference',
    'order_number',
    'title',
    'status',
    'equipment_description',
    'reported_problem',
    'intake_checklist',
    'intake_checklist_template_version',
    'intake_checklist_template_snapshot',
    'equipment_snapshot',
    'diagnosis_internal_notes',
    'diagnosis_customer_summary',
    'diagnosis_completed_at',
    'execution_started_at',
    'execution_started_by_membership_id',
    'final_test_results',
    'technical_completed_at',
    'opened_at',
    'assigned_membership_id',
])]
class ServiceOrder extends Model
{
    use BelongsToCompany;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_READY_FOR_PICKUP = 'ready_for_pickup';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'intake_checklist' => AsArrayObject::class,
            'intake_checklist_template_snapshot' => AsArrayObject::class,
            'equipment_snapshot' => AsArrayObject::class,
            'final_test_results' => AsArrayObject::class,
            'diagnosis_completed_at' => 'datetime',
            'execution_started_at' => 'datetime',
            'technical_completed_at' => 'datetime',
            'opened_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerContact(): BelongsTo
    {
        return $this->belongsTo(CustomerContact::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function intakeChecklistTemplate(): BelongsTo
    {
        return $this->belongsTo(IntakeChecklistTemplate::class);
    }

    public function assignedMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'assigned_membership_id');
    }

    public function executionStartedByMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'execution_started_by_membership_id');
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(ServiceOrderTimelineEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function quoteRevisions(): HasMany
    {
        return $this->hasMany(QuoteRevision::class)->orderBy('revision_number');
    }

    public function executionEntries(): HasMany
    {
        return $this->hasMany(ServiceOrderExecutionEntry::class)->orderBy('id');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(ServiceOrderCharge::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ServiceOrderPayment::class)->orderBy('id');
    }
}
