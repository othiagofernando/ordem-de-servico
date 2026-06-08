<?php

namespace App\Domains\ServiceOrders\Actions;

use App\Domains\ServiceOrders\Models\CompanyServiceOrderCounter;
use App\Domains\ServiceOrders\Models\ServiceOrder;
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

            $counter->forceFill([
                'next_service_order_number' => $currentNumber + 1,
            ])->save();

            $serviceOrder->forceFill([
                'reference' => sprintf('OS-%06d', $currentNumber),
                'order_number' => $currentNumber,
                'status' => ServiceOrder::STATUS_OPEN,
                'opened_at' => now(),
                'title' => $serviceOrder->reported_problem,
            ])->save();

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
}
