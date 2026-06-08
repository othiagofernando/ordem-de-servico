<?php

namespace App\Domains\ServiceOrders\Actions;

use App\Domains\ServiceOrders\Models\CompanyServiceOrderCounter;
use App\Domains\ServiceOrders\Models\IntakeChecklistTemplate;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderTimelineEvent;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class OpenDraftServiceOrder
{
    public function handle(ServiceOrder $serviceOrder): ServiceOrder
    {
        if ($serviceOrder->status !== ServiceOrder::STATUS_DRAFT) {
            throw new UnprocessableEntityHttpException('Only draft intake records can be opened.');
        }

        return DB::transaction(function () use ($serviceOrder): ServiceOrder {
            $counter = $this->lockCounter($serviceOrder->company_id);
            $currentNumber = $counter->next_service_order_number;
            $serviceOrder->loadMissing(['equipment', 'intakeChecklistTemplate']);
            $template = $serviceOrder->intakeChecklistTemplate;

            $counter->forceFill([
                'next_service_order_number' => $currentNumber + 1,
            ])->save();

            $serviceOrder->forceFill([
                'reference' => sprintf('OS-%06d', $currentNumber),
                'order_number' => $currentNumber,
                'status' => ServiceOrder::STATUS_OPEN,
                'opened_at' => now(),
                'title' => $serviceOrder->reported_problem,
                'intake_checklist_template_version' => $template?->version,
                'intake_checklist_template_snapshot' => $this->snapshotTemplate($template),
                'equipment_snapshot' => $this->snapshotEquipment($serviceOrder),
            ])->save();

            app(RecordServiceOrderTimelineEvent::class)->handle(
                $serviceOrder,
                type: 'service_order_opened',
                title: 'Service order opened',
                visibility: ServiceOrderTimelineEvent::VISIBILITY_CUSTOMER,
                actorMembership: currentMembership(),
                payload: [
                    'reference' => $serviceOrder->reference,
                    'order_number' => $serviceOrder->order_number,
                ],
            );

            return $serviceOrder->fresh([
                'customer',
                'customerContact',
                'equipment',
                'assignedMembership',
            ]);
        });
    }

    protected function lockCounter(int $companyId): CompanyServiceOrderCounter
    {
        $counter = CompanyServiceOrderCounter::query()
            ->where('company_id', $companyId)
            ->lockForUpdate()
            ->first();

        if ($counter !== null) {
            return $counter;
        }

        return CompanyServiceOrderCounter::query()->create([
            'company_id' => $companyId,
            'next_draft_number' => 1,
            'next_service_order_number' => 1,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function snapshotTemplate(?IntakeChecklistTemplate $template): ?array
    {
        if ($template === null) {
            return null;
        }

        return [
            'id' => $template->getKey(),
            'category' => $template->category,
            'version' => $template->version,
            'title' => $template->title,
            'items' => $template->items?->getArrayCopy() ?? [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function snapshotEquipment(ServiceOrder $serviceOrder): ?array
    {
        $equipment = $serviceOrder->equipment;

        if ($equipment === null) {
            return null;
        }

        return [
            'id' => $equipment->getKey(),
            'customer_id' => $equipment->customer_id,
            'category' => $equipment->category,
            'brand' => $equipment->brand,
            'model' => $equipment->model,
            'serial_number' => $equipment->serial_number,
            'accessories' => $equipment->accessories,
            'notes' => $equipment->notes,
            'captured_description' => $serviceOrder->equipment_description,
        ];
    }
}
